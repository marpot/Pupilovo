<?php
/** Stage B REST checks. Creates and removes isolated fixtures. */
if (PHP_SAPI !== 'cli') { exit; }
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

use Pupilovo\SupplierHub\Application\CatalogIngestService;
use Pupilovo\SupplierHub\Infrastructure\Database\Schema;

$checks = 0; $user_id = 0; $supplier_id = 0;
function b3_check($condition, string $label): void { if (!$condition) { throw new RuntimeException('FAIL: ' . $label); } ++$GLOBALS['checks']; echo 'PASS: ' . $label . PHP_EOL; }
function b3_request(string $method, string $route, ?array $body = null): WP_REST_Response {
    $parts = wp_parse_url($route); $query = [];
    if (!empty($parts['query'])) { parse_str($parts['query'], $query); }
    $request = new WP_REST_Request($method, '/pupilovo-supplier-hub/v1' . ($parts['path'] ?? $route));
    $request->set_query_params($query);
    if ($body !== null) { $request->set_header('Content-Type', 'application/json'); $request->set_body(wp_json_encode($body)); }
    $response = rest_do_request($request);
    return is_wp_error($response) ? rest_convert_error_to_response($response) : $response;
}

try {
    $run = 'psh-b3-' . bin2hex(random_bytes(5));
    $user_id = wp_insert_user(['user_login' => $run, 'user_email' => $run . '@example.test', 'user_pass' => wp_generate_password(24), 'role' => 'administrator']);
    if (is_wp_error($user_id)) { throw new RuntimeException($user_id->get_error_message()); }
    wp_set_current_user(0);
    b3_check(b3_request('GET', '/catalog')->get_status() >= 400, 'anonymous catalog access denied');
    wp_set_current_user($user_id);

    $created = b3_request('POST', '/suppliers', ['name' => $run, 'sourceType' => 'file_json', 'status' => 'draft', 'sourceConfig' => [], 'fieldMapping' => []]);
    $supplier_id = (int) ($created->get_data()['id'] ?? 0);
    b3_check($created->get_status() === 201 && $supplier_id > 0, 'supplier created for source wizard');

    $source_response = b3_request('PUT', '/suppliers/' . $supplier_id . '/source', [
        'name' => 'Główny JSON', 'sourceType' => 'file_json',
        'config' => ['record_path' => 'data.products', 'default_currency' => 'PLN'],
        'declaresCompleteFeed' => true,
    ]);
    $source = $source_response->get_data();
    b3_check($source_response->get_status() === 200 && $source['sourceType'] === 'file_json', 'source configuration saved');

    $credentials = b3_request('PUT', '/suppliers/' . $supplier_id . '/source/credentials', ['type' => 'bearer', 'token' => 'secret-' . $run]);
    b3_check($credentials->get_status() === 200 && $credentials->get_data()['configured'] === true, 'credentials saved through dedicated endpoint');
    $safe_source = b3_request('GET', '/suppliers/' . $supplier_id . '/source')->get_data();
    b3_check($safe_source['credentialsConfigured'] === true && !str_contains(wp_json_encode($safe_source), 'secret-' . $run), 'source DTO exposes only credential presence');
    global $wpdb;
    $stored_cipher = (string) $wpdb->get_var($wpdb->prepare('SELECT ciphertext FROM ' . Schema::table('secrets') . ' WHERE supplier_id=%d', $supplier_id));
    b3_check($stored_cipher !== '' && !str_contains($stored_cipher, 'secret-' . $run), 'database stores no plaintext token');

    $mapping = ['external_id' => 'id', 'sku' => 'identity.sku', 'ean' => 'identity.ean', 'name' => 'title', 'purchase_price' => 'pricing.net', 'categories' => 'categories', 'images' => 'images.url'];
    $ingested = (new CatalogIngestService())->ingest([
        'supplierId' => $supplier_id, 'sourceId' => $source['id'], 'file' => __DIR__ . '/fixtures/nested-products.json',
        'format' => 'json', 'recordPath' => 'data.products', 'mapping' => $mapping, 'declaredComplete' => true,
    ]);
    b3_check($ingested['created'] === 2, 'catalog fixtures ingested for REST list');
    $catalog = b3_request('GET', '/catalog?search=Karma&supplier_id=' . $supplier_id . '&per_page=1')->get_data();
    b3_check($catalog['total'] === 1 && $catalog['items'][0]['sku'] === 'JSON-1', 'catalog REST applies search, supplier filter and pagination');
    b3_check(!array_key_exists('rawPayload', $catalog['items'][0]), 'catalog list does not expose raw feed payload');

    $deleted = b3_request('DELETE', '/suppliers/' . $supplier_id . '/source/credentials');
    b3_check($deleted->get_data()['configured'] === false, 'credentials can be removed independently');
    echo $checks . ' Stage B REST checks passed.' . PHP_EOL;
} finally {
    global $wpdb;
    if ($supplier_id > 0) {
        $product_ids = $wpdb->get_col($wpdb->prepare('SELECT id FROM ' . Schema::table('catalog_products') . ' WHERE supplier_id=%d', $supplier_id));
        foreach ($product_ids as $id) { $wpdb->delete(Schema::table('product_history'), ['catalog_product_id' => (int) $id], ['%d']); }
        foreach (['product_selections', 'supplier_offers', 'catalog_products', 'supplier_sources', 'secrets', 'feed_runs'] as $suffix) { $wpdb->delete(Schema::table($suffix), ['supplier_id' => $supplier_id], ['%d']); }
        $wpdb->delete(Schema::table('suppliers'), ['id' => $supplier_id], ['%d']);
    }
    if ($user_id > 0) { wp_delete_user($user_id); }
    wp_set_current_user(0);
    echo 'Stage B REST fixtures cleaned up.' . PHP_EOL;
}
