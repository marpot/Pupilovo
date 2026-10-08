<?php

namespace Pupilovo\SupplierHub\REST;

use Pupilovo\SupplierHub\Application\DashboardService;
use Pupilovo\SupplierHub\Domain\SupplierSourceType;
use Pupilovo\SupplierHub\Infrastructure\Repository\SupplierRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined('ABSPATH') || exit;

final class AdminRestController {
    private const NAMESPACE = 'pupilovo-supplier-hub/v1';
    private const STATUSES = ['draft', 'active', 'disabled', 'error'];

    public static function register_routes(): void {
        register_rest_route(self::NAMESPACE, '/dashboard', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'dashboard'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);
        register_rest_route(self::NAMESPACE, '/system', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'system'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);
        register_rest_route(self::NAMESPACE, '/suppliers', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'suppliers'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'create_supplier'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
        ]);
        register_rest_route(self::NAMESPACE, '/suppliers/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'supplier'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [self::class, 'update_supplier'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [self::class, 'delete_supplier'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
        ]);
        register_rest_route(self::NAMESPACE, '/suppliers/(?P<id>\d+)/preview', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'preview_supplier_file'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);
    }

    public static function can_manage(): bool {
        return current_user_can('manage_pupilovo_supplier_hub')
            || current_user_can('manage_woocommerce');
    }

    public static function dashboard(): WP_REST_Response {
        return new WP_REST_Response((new DashboardService())->metrics());
    }

    public static function system(): WP_REST_Response {
        return new WP_REST_Response([
            'pluginVersion' => PUPILOVO_SUPPLIER_HUB_VERSION,
            'schemaVersion' => PUPILOVO_SUPPLIER_HUB_SCHEMA_VERSION,
            'woocommerceAvailable' => class_exists('WooCommerce'),
            'actionSchedulerAvailable' => function_exists('as_enqueue_async_action'),
            'sourceTypes' => SupplierSourceType::all(),
            'apiAdapters' => apply_filters('pupilovo_supplier_hub_api_adapters', []),
        ]);
    }

    public static function suppliers(WP_REST_Request $request): WP_REST_Response {
        $page = max(1, (int) $request->get_param('page'));
        $per_page = min(100, max(1, (int) ($request->get_param('per_page') ?: 20)));
        $search = sanitize_text_field((string) $request->get_param('search'));
        $status = sanitize_key((string) $request->get_param('status'));
        if ($status !== '' && !in_array($status, self::STATUSES, true)) {
            $status = '';
        }

        return new WP_REST_Response(
            (new SupplierRepository())->paginate($page, $per_page, $search, $status)
        );
    }

    public static function supplier(WP_REST_Request $request) {
        $supplier = (new SupplierRepository())->find((int) $request['id']);

        return $supplier
            ? new WP_REST_Response($supplier)
            : new WP_Error('supplier_not_found', 'Nie znaleziono hurtowni.', ['status' => 404]);
    }

    public static function create_supplier(WP_REST_Request $request) {
        $payload = self::validate_payload($request, false);
        if (is_wp_error($payload)) {
            return $payload;
        }

        try {
            return new WP_REST_Response(
                (new SupplierRepository())->create($payload, get_current_user_id()),
                201
            );
        } catch (\RuntimeException $error) {
            return new WP_Error('supplier_save_failed', $error->getMessage(), ['status' => 500]);
        }
    }

    public static function update_supplier(WP_REST_Request $request) {
        $payload = self::validate_payload($request, true);
        if (is_wp_error($payload)) {
            return $payload;
        }

        $supplier = (new SupplierRepository())->update((int) $request['id'], $payload);

        return $supplier
            ? new WP_REST_Response($supplier)
            : new WP_Error('supplier_not_found', 'Nie znaleziono hurtowni.', ['status' => 404]);
    }

    public static function delete_supplier(WP_REST_Request $request) {
        if (!rest_sanitize_boolean($request->get_param('confirm'))) {
            return new WP_Error(
                'confirmation_required',
                'Usunięcie profilu wymaga jawnego potwierdzenia.',
                ['status' => 400]
            );
        }

        $repository = new SupplierRepository();
        if ($repository->find((int) $request['id']) === null) {
            return new WP_Error('supplier_not_found', 'Nie znaleziono hurtowni.', ['status' => 404]);
        }

        $repository->soft_delete((int) $request['id']);

        return new WP_REST_Response([
            'deleted' => true,
            'woocommerceProductsDeleted' => false,
        ]);
    }

    public static function preview_supplier_file(WP_REST_Request $request) {
        $supplier = (new SupplierRepository())->find((int) $request['id']);
        if (!$supplier) {
            return new WP_Error('supplier_not_found', 'Nie znaleziono hurtowni.', ['status' => 404]);
        }
        if (!in_array($supplier['sourceType'], ['file_xml', 'file_csv'], true)) {
            return new WP_Error('invalid_source_type', 'Ten podgląd obsługuje wyłącznie pliki XML i CSV.', ['status' => 400]);
        }

        $files = $request->get_file_params();
        if (!isset($files['feed_file']) || !is_array($files['feed_file'])) {
            return new WP_Error('missing_file', 'Wybierz plik feedu.', ['status' => 400]);
        }

        $profile = [
            'format' => str_ends_with($supplier['sourceType'], 'csv') ? 'csv' : 'xml',
            'record_element' => $supplier['sourceConfig']['record_element'] ?? 'product',
            'delimiter' => $supplier['sourceConfig']['delimiter'] ?? ';',
            'mapping' => $supplier['fieldMapping'],
        ];
        $preview = \Pupilovo_Supplier_Import::preview_upload($files['feed_file'], $profile);
        if (is_wp_error($preview)) {
            $preview->add_data(['status' => 400]);

            return $preview;
        }

        return new WP_REST_Response([
            'items' => $preview,
            'limit' => 20,
            'persisted' => false,
        ]);
    }

    private static function validate_payload(WP_REST_Request $request, bool $partial) {
        $raw = $request->get_json_params();
        if (!is_array($raw)) {
            $raw = $request->get_params();
        }

        if (self::contains_secret_key($raw)) {
            return new WP_Error(
                'credentials_not_supported_here',
                'Dane dostępowe wymagają dedykowanego bezpiecznego endpointu.',
                ['status' => 400]
            );
        }

        $output = [];
        if (!$partial || array_key_exists('name', $raw)) {
            $output['name'] = sanitize_text_field((string) ($raw['name'] ?? ''));
            if ($output['name'] === '') {
                return new WP_Error('invalid_name', 'Podaj nazwę hurtowni.', ['status' => 400]);
            }
        }
        if (!$partial || array_key_exists('sourceType', $raw)) {
            $output['sourceType'] = sanitize_key((string) ($raw['sourceType'] ?? ''));
            if (!SupplierSourceType::is_valid($output['sourceType'])) {
                return new WP_Error('invalid_source_type', 'Nieobsługiwany typ źródła.', ['status' => 400]);
            }
        }
        if (!$partial || array_key_exists('status', $raw)) {
            $output['status'] = sanitize_key((string) ($raw['status'] ?? 'draft'));
            if (!in_array($output['status'], self::STATUSES, true)) {
                return new WP_Error('invalid_status', 'Nieprawidłowy status hurtowni.', ['status' => 400]);
            }
        }

        if (!$partial || array_key_exists('adapterKey', $raw)) {
            $output['adapterKey'] = sanitize_key((string) ($raw['adapterKey'] ?? ''));
        }
        if (array_key_exists('syncEnabled', $raw)) {
            $output['syncEnabled'] = rest_sanitize_boolean($raw['syncEnabled']);
        }
        if (!$partial || array_key_exists('sourceConfig', $raw)) {
            $output['sourceConfig'] = self::sanitize_associative_array($raw['sourceConfig'] ?? []);
        }
        if (!$partial || array_key_exists('fieldMapping', $raw)) {
            $output['fieldMapping'] = self::sanitize_associative_array($raw['fieldMapping'] ?? []);
        }

        return $output;
    }

    private static function sanitize_associative_array($value): array {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $key => $item) {
            $safe_key = sanitize_key((string) $key);
            if ($safe_key === '') {
                continue;
            }
            $result[$safe_key] = is_array($item)
                ? self::sanitize_associative_array($item)
                : sanitize_text_field((string) $item);
        }

        return $result;
    }

    private static function contains_secret_key(array $value): bool {
        $secret_keys = ['credentials', 'secret', 'password', 'token', 'apikey', 'api_key', 'authorization'];
        foreach ($value as $key => $item) {
            if (in_array(strtolower((string) $key), $secret_keys, true)) {
                return true;
            }
            if (is_array($item) && self::contains_secret_key($item)) {
                return true;
            }
        }

        return false;
    }
}
