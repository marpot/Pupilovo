<?php
/**
 * Stage A integration checks. Run inside the local WordPress container.
 * The script removes only fixtures carrying its unique run identifier.
 */

if (PHP_SAPI !== 'cli') {
    exit;
}

define('DISABLE_WP_CRON', true);
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

use Pupilovo\SupplierHub\Infrastructure\Database\Schema;

$run = 'psh-stage-a-' . bin2hex(random_bytes(6));
$user_id = 0;
$supplier_id = 0;
$checks = 0;

function psh_check($condition, string $label): void {
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    ++$GLOBALS['checks'];
    echo 'PASS: ' . $label . PHP_EOL;
}

function psh_request(string $method, string $route, array $params = []): WP_REST_Response {
    $request = new WP_REST_Request($method, '/pupilovo-supplier-hub/v1' . $route);
    if ($method === 'GET') {
        $request->set_query_params($params);
    } else {
        $request->set_body_params($params);
    }
    $response = rest_do_request($request);
    if (is_wp_error($response)) {
        return rest_convert_error_to_response($response);
    }

    return $response;
}

try {
    psh_check(class_exists(Schema::class), 'plugin bootstrap is loaded');
    Schema::activate();

    global $wpdb;
    foreach (['suppliers', 'catalog_products', 'supplier_categories', 'category_mappings', 'product_selections', 'product_links', 'pricing_rules', 'jobs', 'job_items', 'logs'] as $suffix) {
        $table = Schema::table($suffix);
        psh_check($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table, 'schema table exists: ' . $suffix);
    }

    wp_set_current_user(0);
    psh_check(psh_request('GET', '/dashboard')->get_status() >= 400, 'anonymous dashboard request is denied');

    $user_id = wp_insert_user([
        'user_login' => $run,
        'user_email' => $run . '@example.test',
        'user_pass' => wp_generate_password(28, true, true),
        'role' => 'administrator',
    ]);
    if (is_wp_error($user_id)) {
        throw new RuntimeException($user_id->get_error_message());
    }
    wp_set_current_user($user_id);
    psh_check(current_user_can('manage_pupilovo_supplier_hub'), 'administrator receives Supplier Hub capability');

    $invalid = psh_request('POST', '/suppliers', [
        'name' => $run,
        'sourceType' => 'file_xml',
        'sourceConfig' => ['auth' => ['password' => 'must-not-be-stored']],
    ]);
    psh_check($invalid->get_status() === 400, 'generic supplier endpoint rejects secrets');

    $created = psh_request('POST', '/suppliers', [
        'name' => $run,
        'sourceType' => 'file_xml',
        'status' => 'draft',
        'sourceConfig' => ['record_element' => 'product'],
        'fieldMapping' => ['external_id' => 'id', 'sku' => 'sku', 'name' => 'name'],
    ]);
    $created_data = $created->get_data();
    $supplier_id = (int) ($created_data['id'] ?? 0);
    psh_check($created->get_status() === 201 && $supplier_id > 0, 'supplier draft is created through REST');
    psh_check(!array_key_exists('credentials', $created_data), 'supplier response does not expose credentials');

    $list = psh_request('GET', '/suppliers', ['search' => $run])->get_data();
    psh_check(($list['total'] ?? 0) === 1 && ($list['items'][0]['id'] ?? 0) === $supplier_id, 'supplier list is filtered and paginated');

    $dashboard = psh_request('GET', '/dashboard')->get_data();
    psh_check(isset($dashboard['connectedSuppliers'], $dashboard['catalogProducts'], $dashboard['selectedProducts']), 'dashboard exposes real metric contract');

    $missing_confirmation = psh_request('DELETE', '/suppliers/' . $supplier_id);
    psh_check($missing_confirmation->get_status() === 400, 'supplier deletion requires explicit confirmation');
    $deleted = psh_request('DELETE', '/suppliers/' . $supplier_id, ['confirm' => true]);
    psh_check($deleted->get_status() === 200 && $deleted->get_data()['woocommerceProductsDeleted'] === false, 'soft delete never deletes WooCommerce products');

    $table = Schema::table('suppliers');
    $status = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$table} WHERE id = %d", $supplier_id));
    psh_check($status === 'deleted', 'supplier remains auditable after soft delete');

    $xml = new ReflectionMethod(Pupilovo_Supplier_Import::class, 'xml');
    $xml_rows = $xml->invoke(null, __DIR__ . '/fixtures/simple-products.xml', [
        'record_element' => 'product',
        'mapping' => ['external_id' => 'id', 'sku' => 'sku', 'name' => 'name'],
    ]);
    psh_check(is_array($xml_rows) && count($xml_rows) === 2 && $xml_rows[0]['sku'] === 'DOG-BOWL-01', 'XMLReader preview maps fixture records');

    $csv = new ReflectionMethod(Pupilovo_Supplier_Import::class, 'csv');
    $csv_rows = $csv->invoke(null, __DIR__ . '/fixtures/simple-products.csv', [
        'delimiter' => ';',
        'mapping' => ['external_id' => 'id', 'sku' => 'sku', 'name' => 'name'],
    ]);
    psh_check(is_array($csv_rows) && count($csv_rows) === 2 && $csv_rows[1]['sku'] === 'CAT-TOY-02', 'CSV preview maps fixture records');

    echo $checks . ' Stage A integration checks passed.' . PHP_EOL;
} finally {
    global $wpdb;
    if ($supplier_id > 0 && class_exists(Schema::class)) {
        $wpdb->delete(Schema::table('suppliers'), ['id' => $supplier_id], ['%d']);
    }
    if ($user_id > 0) {
        wp_delete_user($user_id);
    }
    wp_set_current_user(0);
    echo 'Stage A fixtures cleaned up.' . PHP_EOL;
}
