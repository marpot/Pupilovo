<?php

namespace Pupilovo\SupplierHub\REST;

use Pupilovo\SupplierHub\Domain\SupplierSourceType;
use Pupilovo\SupplierHub\Infrastructure\Feed\FeedParserFactory;
use Pupilovo\SupplierHub\Infrastructure\Http\SourceHttpClient;
use Pupilovo\SupplierHub\Infrastructure\Repository\SecretRepository;
use Pupilovo\SupplierHub\Infrastructure\Repository\SourceRepository;
use Pupilovo\SupplierHub\Infrastructure\Repository\SupplierRepository;
use Pupilovo\SupplierHub\Infrastructure\Security\HeaderPolicy;
use Pupilovo\SupplierHub\Infrastructure\Security\SourceUrlGuard;
use Pupilovo\SupplierHub\Application\CatalogImportManager;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined('ABSPATH') || exit;

final class SourceRestController {
    private const NS = 'pupilovo-supplier-hub/v1';

    public static function register_routes(): void {
        register_rest_route(self::NS, '/suppliers/(?P<id>\d+)/source', [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [self::class, 'get'], 'permission_callback' => [AdminRestController::class, 'can_manage']],
            ['methods' => WP_REST_Server::EDITABLE, 'callback' => [self::class, 'save'], 'permission_callback' => [AdminRestController::class, 'can_manage']],
        ]);
        register_rest_route(self::NS, '/suppliers/(?P<id>\d+)/source/credentials', [
            ['methods' => WP_REST_Server::EDITABLE, 'callback' => [self::class, 'credentials'], 'permission_callback' => [AdminRestController::class, 'can_manage']],
            ['methods' => WP_REST_Server::DELETABLE, 'callback' => [self::class, 'delete_credentials'], 'permission_callback' => [AdminRestController::class, 'can_manage']],
        ]);
        register_rest_route(self::NS, '/suppliers/(?P<id>\d+)/source/inspect', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [self::class, 'inspect'],
            'permission_callback' => [AdminRestController::class, 'can_manage'],
        ]);
        register_rest_route(self::NS, '/suppliers/(?P<id>\d+)/source/ingest', ['methods'=>WP_REST_Server::CREATABLE,'callback'=>[self::class,'ingest'],'permission_callback'=>[AdminRestController::class,'can_manage']]);
    }

    public static function get(WP_REST_Request $request) {
        if (!(new SupplierRepository())->find((int) $request['id'])) {
            return new WP_Error('supplier_not_found', 'Nie znaleziono hurtowni.', ['status' => 404]);
        }

        return new WP_REST_Response((new SourceRepository())->find_primary((int) $request['id']));
    }

    public static function save(WP_REST_Request $request) {
        $supplier_id = (int) $request['id'];
        if (!(new SupplierRepository())->find($supplier_id)) {
            return new WP_Error('supplier_not_found', 'Nie znaleziono hurtowni.', ['status' => 404]);
        }
        $body = $request->get_json_params();
        $body = is_array($body) ? $body : $request->get_params();
        $type = sanitize_key((string) ($body['sourceType'] ?? ''));
        if (!SupplierSourceType::is_valid($type)) {
            return new WP_Error('invalid_source_type', 'Nieobsługiwany typ źródła.', ['status' => 400]);
        }
        $location = null;
        if (str_starts_with($type, 'url_')) {
            $location = esc_url_raw((string) ($body['location'] ?? ''), ['http', 'https']);
            if ($location === '') {
                return new WP_Error('invalid_source_url', 'Podaj prawidłowy adres URL.', ['status' => 400]);
            }
        }
        $config = self::sanitize_config($body['config'] ?? []);
        try {
            $source = (new SourceRepository())->save_primary($supplier_id, [
                'name' => sanitize_text_field((string) ($body['name'] ?? 'Źródło główne')),
                'sourceType' => $type, 'location' => $location, 'config' => $config,
                'allowInsecureHttp' => rest_sanitize_boolean($body['allowInsecureHttp'] ?? false),
                'declaresCompleteFeed' => rest_sanitize_boolean($body['declaresCompleteFeed'] ?? false),
            ]);

            return new WP_REST_Response($source);
        } catch (\RuntimeException $error) {
            return new WP_Error('source_save_failed', $error->getMessage(), ['status' => 500]);
        }
    }

    public static function credentials(WP_REST_Request $request) {
        $supplier_id = (int) $request['id'];
        $source = (new SourceRepository())->find_primary($supplier_id);
        if (!$source) { return new WP_Error('source_not_found', 'Najpierw zapisz źródło.', ['status' => 404]); }
        $body = $request->get_json_params();
        $body = is_array($body) ? $body : [];
        $type = sanitize_key((string) ($body['type'] ?? 'none'));
        $secret = ['type' => $type];
        if ($type === 'basic') {
            $secret['username'] = sanitize_text_field((string) ($body['username'] ?? ''));
            $secret['password'] = (string) ($body['password'] ?? '');
            if ($secret['username'] === '' || $secret['password'] === '') { return new WP_Error('invalid_credentials', 'Podaj login i hasło.', ['status' => 400]); }
        } elseif ($type === 'bearer') {
            $secret['token'] = trim((string) ($body['token'] ?? ''));
            if ($secret['token'] === '') { return new WP_Error('invalid_credentials', 'Podaj token Bearer.', ['status' => 400]); }
        } elseif ($type === 'headers') {
            try { $secret['headers'] = (new HeaderPolicy())->sanitize(is_array($body['headers'] ?? null) ? $body['headers'] : []); }
            catch (\InvalidArgumentException $error) { return new WP_Error('invalid_headers', $error->getMessage(), ['status' => 400]); }
            if ($secret['headers'] === []) { return new WP_Error('invalid_credentials', 'Dodaj co najmniej jeden nagłówek.', ['status' => 400]); }
        } elseif ($type !== 'none') {
            return new WP_Error('invalid_auth_type', 'Nieobsługiwany typ uwierzytelnienia.', ['status' => 400]);
        }
        $repo = new SecretRepository();
        $source_repo = new SourceRepository();
        if ($type === 'none') {
            $repo->delete_for_supplier($supplier_id); $source_repo->attach_secret((int) $source['id'], null);
        } else {
            $secret_id = $repo->put($supplier_id, 'primary_source', $secret); $source_repo->attach_secret((int) $source['id'], $secret_id);
        }

        return new WP_REST_Response(['configured' => $type !== 'none', 'type' => $type]);
    }

    public static function delete_credentials(WP_REST_Request $request): WP_REST_Response {
        $supplier_id = (int) $request['id'];
        $source = (new SourceRepository())->find_primary($supplier_id);
        (new SecretRepository())->delete_for_supplier($supplier_id);
        if ($source) { (new SourceRepository())->attach_secret((int) $source['id'], null); }

        return new WP_REST_Response(['configured' => false]);
    }

    public static function inspect(WP_REST_Request $request) {
        $supplier_id = (int) $request['id'];
        $source = (new SourceRepository())->find_primary($supplier_id);
        if (!$source) { return new WP_Error('source_not_found', 'Najpierw zapisz źródło.', ['status' => 404]); }
        $format = self::format($source['sourceType']);
        if ($format === null) { return new WP_Error('adapter_required', 'Źródło API wymaga zarejestrowanego adaptera.', ['status' => 400]); }
        $path = '';
        $downloaded = false;
        if (str_starts_with($source['sourceType'], 'file_')) {
            $files = $request->get_file_params();
            $file = $files['feed_file'] ?? null;
            if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
                return new WP_Error('invalid_upload', 'Prześlij prawidłowy plik feedu.', ['status' => 400]);
            }
            $path = (string) $file['tmp_name'];
            $size = filesize($path);
            if ($size === false || $size < 1 || $size > SourceHttpClient::DEFAULT_MAX_BYTES) { return new WP_Error('source_size_invalid', 'Plik jest pusty albo przekracza 100 MiB.', ['status' => 400]); }
        } else {
            $headers = self::authorization_headers((new SecretRepository())->get($supplier_id, 'primary_source'));
            $result = (new SourceHttpClient())->download((string) $source['location'], $headers, $source['allowInsecureHttp']);
            if (is_wp_error($result)) { $result->add_data(['status' => 400]); return $result; }
            $path = $result['path']; $downloaded = true;
        }
        try {
            $parser = (new FeedParserFactory())->create($format, $path, $source['config']);
            $record_path = (string) ($source['config']['record_path'] ?? '');
            $inspection = $parser->inspect($record_path, 5);

            return new WP_REST_Response([...$inspection, 'format' => $format, 'recordPath' => $record_path, 'persisted' => false]);
        } catch (\Throwable $error) {
            return new WP_Error('feed_inspection_failed', $error->getMessage(), ['status' => 400]);
        } finally {
            if ($downloaded && $path !== '') { wp_delete_file($path); }
        }
    }

    public static function ingest(WP_REST_Request$request){$files=$request->get_file_params();try{return new WP_REST_Response((new CatalogImportManager())->enqueue((int)$request['id'],get_current_user_id(),isset($files['feed_file'])&&is_array($files['feed_file'])?$files['feed_file']:null),202);}catch(\InvalidArgumentException$error){return new WP_Error('invalid_catalog_source',$error->getMessage(),['status'=>400]);}catch(\Throwable$error){return new WP_Error('catalog_queue_failed',$error->getMessage(),['status'=>500]);}}

    private static function authorization_headers(?array $secret): array {
        if (!$secret || ($secret['type'] ?? 'none') === 'none') { return []; }
        return match ($secret['type']) {
            'basic' => ['authorization' => 'Basic ' . base64_encode($secret['username'] . ':' . $secret['password'])],
            'bearer' => ['authorization' => 'Bearer ' . $secret['token']],
            'headers' => $secret['headers'] ?? [],
            default => [],
        };
    }

    private static function format(string $type): ?string {
        foreach (['xml', 'csv', 'tsv', 'json'] as $format) { if (str_ends_with($type, '_' . $format)) { return $format; } }
        return null;
    }

    private static function sanitize_config($config): array {
        if (!is_array($config)) { return []; }
        $allowed = ['record_path', 'delimiter', 'default_currency', 'encoding', 'max_records'];
        $result = [];
        foreach ($allowed as $key) { if (isset($config[$key]) && is_scalar($config[$key])) { $result[$key] = sanitize_text_field((string) $config[$key]); } }

        return $result;
    }
}
