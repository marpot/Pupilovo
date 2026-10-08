<?php

namespace Pupilovo\SupplierHub\REST;

use Pupilovo\SupplierHub\Infrastructure\Repository\CatalogQueryRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined('ABSPATH') || exit;

final class CatalogRestController {
    public static function register_routes(): void {
        register_rest_route('pupilovo-supplier-hub/v1', '/catalog', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'list'],
            'permission_callback' => [AdminRestController::class, 'can_manage'],
        ]);
    }

    public static function list(WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response((new CatalogQueryRepository())->paginate([
            'page' => $request->get_param('page'), 'perPage' => $request->get_param('per_page'),
            'supplierId' => $request->get_param('supplier_id'), 'search' => sanitize_text_field((string) $request->get_param('search')),
            'availability' => sanitize_key((string) $request->get_param('availability')), 'minPrice' => $request->get_param('min_price'),
            'maxPrice' => $request->get_param('max_price'), 'selected' => sanitize_key((string) $request->get_param('selected')),
            'sort' => sanitize_key((string) $request->get_param('sort')), 'direction' => sanitize_key((string) $request->get_param('direction')),
        ]));
    }
}
