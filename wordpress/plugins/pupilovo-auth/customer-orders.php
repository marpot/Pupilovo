<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    register_rest_route('pupilovo/v1', '/account/orders', [
        'methods' => 'GET',
        'callback' => 'pupilovo_get_customer_orders',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
        'args' => [
            'page' => [
                'default' => 1,
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 100000,
                'validate_callback' => 'rest_validate_request_arg',
                'sanitize_callback' => 'rest_sanitize_request_arg',
            ],
        ],
    ]);
});

/** Query WooCommerce's data store (supports both HPOS and legacy storage). */
function pupilovo_get_customer_orders(WP_REST_Request $request)
{
    if (!function_exists('wc_get_orders')) {
        return new WP_Error('woocommerce_unavailable', 'Historia zamówień jest chwilowo niedostępna.', ['status' => 503]);
    }

    $page = (int) $request->get_param('page');
    $result = wc_get_orders([
        'type' => 'shop_order',
        'customer_id' => get_current_user_id(),
        // Exclude unfinished checkout drafts and refunds as standalone orders.
        'status' => array_keys(wc_get_order_statuses()),
        'limit' => 10,
        'page' => $page,
        'paginate' => true,
        'orderby' => 'ID',
        'order' => 'DESC',
    ]);

    $orders = [];
    foreach ($result->orders as $order) {
        // Defense in depth if another plugin modifies the WooCommerce query.
        if ((int) $order->get_customer_id() !== get_current_user_id()) {
            continue;
        }
        $items = [];
        foreach ($order->get_items('line_item') as $item) {
            $items[] = [
                'id' => $item->get_id(),
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
            ];
        }
        $created = $order->get_date_created();
        $orders[] = [
            'id' => $order->get_id(),
            'number' => (string) $order->get_order_number(),
            'createdAt' => $created ? $created->date(DATE_ATOM) : null,
            'status' => $order->get_status(),
            'statusLabel' => wc_get_order_status_name($order->get_status()),
            'total' => $order->get_total(),
            'currency' => $order->get_currency(),
            'items' => $items,
        ];
    }

    $response = new WP_REST_Response([
        'orders' => $orders,
        'page' => $page,
        'total' => (int) $result->total,
        'totalPages' => (int) $result->max_num_pages,
    ]);
    $response->header('Cache-Control', 'private, no-store, max-age=0');
    return $response;
}
