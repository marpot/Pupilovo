<?php

namespace Pupilovo\SupplierHub\REST;

use Pupilovo\SupplierHub\Application\WooCategoryService;
use Pupilovo\SupplierHub\Domain\Category\CategoryMatcher;
use Pupilovo\SupplierHub\Infrastructure\Repository\CategoryRepository;
use Pupilovo\SupplierHub\Infrastructure\Repository\SelectionRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined('ABSPATH') || exit;

final class CategorySelectionRestController {
    private const NS = 'pupilovo-supplier-hub/v1';

    public static function register_routes(): void {
        register_rest_route(self::NS, '/categories', ['methods' => WP_REST_Server::READABLE, 'callback' => [self::class, 'categories'], 'permission_callback' => [AdminRestController::class, 'can_manage']]);
        register_rest_route(self::NS, '/categories/(?P<id>\d+)/suggestions', ['methods' => WP_REST_Server::READABLE, 'callback' => [self::class, 'suggestions'], 'permission_callback' => [AdminRestController::class, 'can_manage']]);
        register_rest_route(self::NS, '/category-mappings', ['methods' => WP_REST_Server::CREATABLE, 'callback' => [self::class, 'map'], 'permission_callback' => [AdminRestController::class, 'can_manage']]);
        register_rest_route(self::NS, '/woocommerce/categories', ['methods' => WP_REST_Server::READABLE, 'callback' => [self::class, 'woo_categories'], 'permission_callback' => [AdminRestController::class, 'can_manage']]);
        register_rest_route(self::NS, '/selections', [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [self::class, 'selection_count'], 'permission_callback' => [AdminRestController::class, 'can_manage']],
            ['methods' => WP_REST_Server::EDITABLE, 'callback' => [self::class, 'select'], 'permission_callback' => [AdminRestController::class, 'can_manage']],
        ]);
        register_rest_route(self::NS, '/selections/filter', ['methods' => WP_REST_Server::EDITABLE, 'callback' => [self::class, 'select_filter'], 'permission_callback' => [AdminRestController::class, 'can_manage']]);
    }

    public static function categories(WP_REST_Request $request) {
        $supplier_id = (int) $request->get_param('supplier_id');
        if ($supplier_id < 1) { return new WP_Error('supplier_required', 'Wybierz hurtownię.', ['status' => 400]); }
        return new WP_REST_Response(['items' => (new CategoryRepository())->list($supplier_id)]);
    }

    public static function suggestions(WP_REST_Request $request) {
        $category = (new CategoryRepository())->find((int) $request['id']);
        if (!$category) { return new WP_Error('category_not_found', 'Nie znaleziono kategorii.', ['status' => 404]); }
        $synonyms = get_option('pupilovo_supplier_hub_category_synonyms', []);
        return new WP_REST_Response(['items' => (new CategoryMatcher())->suggest($category, (new WooCategoryService())->tree_flat(), is_array($synonyms) ? $synonyms : [])]);
    }

    public static function woo_categories(): WP_REST_Response { return new WP_REST_Response(['items' => (new WooCategoryService())->tree_flat()]); }

    public static function map(WP_REST_Request $request) {
        $body = $request->get_json_params(); $body = is_array($body) ? $body : [];
        $category_id = (int) ($body['categoryId'] ?? 0); $decision = sanitize_key((string) ($body['decision'] ?? ''));
        if (!in_array($decision, ['map', 'create', 'skip'], true)) { return new WP_Error('invalid_decision', 'Nieprawidłowa decyzja mapowania.', ['status' => 400]); }
        $term_id = null;
        if ($decision === 'map') {
            $term_id = (int) ($body['termId'] ?? 0);
            if ($term_id < 1 || !term_exists($term_id, 'product_cat')) { return new WP_Error('invalid_term', 'Nie znaleziono kategorii WooCommerce.', ['status' => 400]); }
        } elseif ($decision === 'create') {
            if (!rest_sanitize_boolean($body['confirmCreate'] ?? false)) { return new WP_Error('confirmation_required', 'Utworzenie kategorii wymaga potwierdzenia.', ['status' => 400]); }
            $category = (new CategoryRepository())->find($category_id);
            if (!$category) { return new WP_Error('category_not_found', 'Nie znaleziono kategorii dostawcy.', ['status' => 404]); }
            $created = wp_insert_term(sanitize_text_field((string) ($body['name'] ?? $category['name'])), 'product_cat', ['parent' => max(0, (int) ($body['parentTermId'] ?? 0))]);
            if (is_wp_error($created)) { return $created; }
            $term_id = (int) $created['term_id'];
        }
        try { (new CategoryRepository())->save_mapping($category_id, $term_id, $decision === 'map' ? 'approved' : $decision, 'manual', isset($body['confidence']) ? (float) $body['confidence'] : null, get_current_user_id()); }
        catch (\InvalidArgumentException $error) { return new WP_Error('category_not_found', $error->getMessage(), ['status' => 404]); }
        return new WP_REST_Response(['saved' => true, 'termId' => $term_id]);
    }

    public static function selection_count(): WP_REST_Response { return new WP_REST_Response(['count' => (new SelectionRepository())->count()]); }

    public static function select(WP_REST_Request $request) {
        $body = $request->get_json_params(); $body = is_array($body) ? $body : [];
        try { $changed = (new SelectionRepository())->set(is_array($body['productIds'] ?? null) ? $body['productIds'] : [], rest_sanitize_boolean($body['selected'] ?? true), get_current_user_id()); }
        catch (\InvalidArgumentException $error) { return new WP_Error('invalid_selection', $error->getMessage(), ['status' => 400]); }
        return new WP_REST_Response(['changed' => $changed, 'count' => (new SelectionRepository())->count()]);
    }

    public static function select_filter(WP_REST_Request $request): WP_REST_Response {
        $body = $request->get_json_params(); $body = is_array($body) ? $body : [];
        $filter = is_array($body['filter'] ?? null) ? $body['filter'] : [];
        $safe = ['supplierId' => (int) ($filter['supplierId'] ?? 0), 'category' => sanitize_text_field((string) ($filter['category'] ?? '')), 'availability' => sanitize_key((string) ($filter['availability'] ?? '')), 'search' => sanitize_text_field((string) ($filter['search'] ?? ''))];
        $changed = (new SelectionRepository())->set_by_filter($safe, rest_sanitize_boolean($body['selected'] ?? true), get_current_user_id());
        return new WP_REST_Response(['changed' => $changed, 'count' => (new SelectionRepository())->count()]);
    }
}
