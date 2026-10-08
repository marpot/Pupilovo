<?php

namespace Pupilovo\SupplierHub\REST;

use Pupilovo\SupplierHub\Application\ImportExecutor;
use Pupilovo\SupplierHub\Application\ImportPreviewService;
use Pupilovo\SupplierHub\Application\JobQueue;
use Pupilovo\SupplierHub\Infrastructure\Repository\ImportJobRepository;
use Pupilovo\SupplierHub\Infrastructure\Repository\PricingRuleRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined('ABSPATH') || exit;

final class ImportRestController {
    private const NS = 'pupilovo-supplier-hub/v1';

    public static function register_routes(): void {
        register_rest_route(self::NS, '/pricing-rules', [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [self::class, 'pricing_rules'], 'permission_callback' => [AdminRestController::class, 'can_manage']],
            ['methods' => WP_REST_Server::CREATABLE, 'callback' => [self::class, 'save_pricing_rule'], 'permission_callback' => [AdminRestController::class, 'can_manage']],
        ]);
        register_rest_route(self::NS, '/pricing-rules/(?P<id>\d+)', ['methods' => WP_REST_Server::DELETABLE, 'callback' => [self::class, 'delete_pricing_rule'], 'permission_callback' => [AdminRestController::class, 'can_manage']]);
        register_rest_route(self::NS, '/import-preview', ['methods' => WP_REST_Server::CREATABLE, 'callback' => [self::class, 'preview'], 'permission_callback' => [AdminRestController::class, 'can_manage']]);
        register_rest_route(self::NS, '/jobs/(?P<id>\d+)', ['methods' => WP_REST_Server::READABLE, 'callback' => [self::class, 'job'], 'permission_callback' => [AdminRestController::class, 'can_manage']]);
        register_rest_route(self::NS, '/jobs/(?P<id>\d+)/approve', ['methods' => WP_REST_Server::CREATABLE, 'callback' => [self::class, 'approve'], 'permission_callback' => [AdminRestController::class, 'can_manage']]);
        register_rest_route(self::NS, '/jobs/(?P<id>\d+)/cancel', ['methods' => WP_REST_Server::CREATABLE, 'callback' => [self::class, 'cancel'], 'permission_callback' => [AdminRestController::class, 'can_manage']]);
    }

    public static function pricing_rules(WP_REST_Request $request) {
        $supplier_id = (int) $request->get_param('supplier_id');
        if ($supplier_id < 1) { return new WP_Error('supplier_required', 'Wybierz hurtownię.', ['status' => 400]); }
        return new WP_REST_Response(['items' => (new PricingRuleRepository())->list($supplier_id)]);
    }

    public static function save_pricing_rule(WP_REST_Request $request) {
        $body = $request->get_json_params(); $body = is_array($body) ? $body : [];
        $supplier_id = (int) ($body['supplierId'] ?? 0);
        if ($supplier_id < 1 || !is_array($body['config'] ?? null)) { return new WP_Error('invalid_pricing_rule', 'Hurtownia i konfiguracja reguły są wymagane.', ['status' => 400]); }
        try { return new WP_REST_Response((new PricingRuleRepository())->save($supplier_id, $body), 201); }
        catch (\Throwable $error) { return new WP_Error('pricing_rule_failed', $error->getMessage(), ['status' => 500]); }
    }

    public static function delete_pricing_rule(WP_REST_Request $request) {
        $supplier_id = (int) $request->get_param('supplier_id');
        if ($supplier_id < 1) { return new WP_Error('supplier_required', 'Wybierz hurtownię.', ['status' => 400]); }
        return new WP_REST_Response(['deleted' => (new PricingRuleRepository())->delete($supplier_id, (int) $request['id'])]);
    }

    public static function preview(WP_REST_Request $request) {
        $body = $request->get_json_params(); $body = is_array($body) ? $body : [];
        try {
            $supplier_id = isset($body['supplierId']) && (int) $body['supplierId'] > 0 ? (int) $body['supplierId'] : null;
            return new WP_REST_Response((new ImportPreviewService())->create($supplier_id, get_current_user_id(), $body), 201);
        } catch (\Throwable $error) {
            return new WP_Error('preview_failed', $error->getMessage(), ['status' => 400]);
        }
    }

    public static function job(WP_REST_Request $request) {
        $repository = new ImportJobRepository(); $job = $repository->get((int) $request['id']);
        if (!$job) { return new WP_Error('job_not_found', 'Nie znaleziono zadania.', ['status' => 404]); }
        return new WP_REST_Response(['job' => $job, 'items' => $repository->items((int) $request['id'])]);
    }

    public static function approve(WP_REST_Request $request) {
        $body = $request->get_json_params(); $body = is_array($body) ? $body : [];
        if (!rest_sanitize_boolean($body['confirm'] ?? false)) { return new WP_Error('confirmation_required', 'Import wymaga jawnego potwierdzenia.', ['status' => 400]); }
        try { $action_id=(new JobQueue())->enqueue_import((int)$request['id'],get_current_user_id());return new WP_REST_Response(['queued'=>true,'actionId'=>$action_id,'job'=>(new ImportJobRepository())->get((int)$request['id'])],202); }
        catch (\InvalidArgumentException $error) { return new WP_Error('job_not_found', $error->getMessage(), ['status' => 404]); }
        catch (\Throwable $error) { return new WP_Error('import_failed', $error->getMessage(), ['status' => 409]); }
    }

    public static function cancel(WP_REST_Request $request): WP_REST_Response { return new WP_REST_Response(['cancelledActions'=>(new JobQueue())->cancel((int)$request['id'])]); }
}
