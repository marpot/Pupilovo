<?php
/** Dry-run and idempotent WooCommerce import integration checks. */
if (PHP_SAPI !== 'cli') { exit; }
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

use Pupilovo\SupplierHub\Application\CatalogIngestService;
use Pupilovo\SupplierHub\Application\ImportExecutor;
use Pupilovo\SupplierHub\Application\JobQueue;
use Pupilovo\SupplierHub\Application\SyncManager;
use Pupilovo\SupplierHub\Infrastructure\Database\Schema;
use Pupilovo\SupplierHub\Infrastructure\Repository\CategoryRepository;
use Pupilovo\SupplierHub\Infrastructure\Repository\PricingRuleRepository;
use Pupilovo\SupplierHub\Infrastructure\Repository\SelectionRepository;
use Pupilovo\SupplierHub\Infrastructure\Repository\SupplierRepository;

$checks = 0; $supplier_id = 0; $user_id = 0; $term_id = 0; $temporary = ''; $product_ids = [];
function d_check($condition, string $label): void { if (!$condition) { throw new RuntimeException('FAIL: ' . $label); } ++$GLOBALS['checks']; echo 'PASS: ' . $label . PHP_EOL; }
function d_request(string $method, string $route, ?array $body = null): WP_REST_Response {
    $request = new WP_REST_Request($method, '/pupilovo-supplier-hub/v1' . $route);
    if ($body !== null) { $request->set_header('Content-Type', 'application/json'); $request->set_body(wp_json_encode($body)); }
    $response = rest_do_request($request);
    return is_wp_error($response) ? rest_convert_error_to_response($response) : $response;
}

try {
    global $wpdb;
    $run = 'psh-d-' . bin2hex(random_bytes(5));
    $user_id = wp_insert_user(['user_login' => $run, 'user_email' => $run . '@example.test', 'user_pass' => wp_generate_password(24), 'role' => 'administrator']);
    if (is_wp_error($user_id)) { throw new RuntimeException($user_id->get_error_message()); }
    wp_set_current_user($user_id);
    $supplier = (new SupplierRepository())->create(['name' => $run, 'sourceType' => 'file_json', 'adapterKey' => '', 'status' => 'draft', 'sourceConfig' => [], 'fieldMapping' => []], $user_id);
    $supplier_id = (int) $supplier['id'];

    $temporary = tempnam(sys_get_temp_dir(), 'psh-stage-d-');
    file_put_contents($temporary, wp_json_encode(['products' => [
        ['id' => 'safe-1', 'sku' => $run . '-SAFE', 'ean' => '5901234123457', 'name' => 'Bezpieczny produkt', 'price' => 100, 'tax' => 23, 'stock' => 7, 'category' => 'Legowiska'],
        ['id' => 'conflict-1', 'sku' => $run . '-CONFLICT', 'ean' => '', 'name' => 'Konflikt SKU', 'price' => 50, 'tax' => 23, 'stock' => 2, 'category' => 'Legowiska'],
    ]]));
    (new CatalogIngestService())->ingest([
        'supplierId' => $supplier_id, 'file' => $temporary, 'format' => 'json', 'recordPath' => 'products', 'declaredComplete' => true,
        'mapping' => ['external_id' => 'id', 'sku' => 'sku', 'ean' => 'ean', 'name' => 'name', 'purchase_price' => 'price', 'tax_rate' => 'tax', 'stock' => 'stock', 'categories' => 'category'],
    ]);

    $term = wp_insert_term('Legowiska ' . $run, 'product_cat');
    if (is_wp_error($term)) { throw new RuntimeException($term->get_error_message()); }
    $term_id = (int) $term['term_id'];
    $category_id = (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . Schema::table('supplier_categories') . ' WHERE supplier_id=%d AND name=%s', $supplier_id, 'Legowiska'));
    (new CategoryRepository())->save_mapping($category_id, $term_id, 'approved', 'manual', 1.0, $user_id);

    (new PricingRuleRepository())->save($supplier_id, ['scopeType' => 'supplier', 'priority' => 100, 'active' => true, 'config' => ['mode' => 'markup', 'value' => 20, 'purchasePriceIncludesTax' => false, 'rounding' => ['increment' => 0.01]]]);
    $catalog_ids = array_map('intval', $wpdb->get_col($wpdb->prepare('SELECT id FROM ' . Schema::table('catalog_products') . ' WHERE supplier_id=%d ORDER BY id', $supplier_id)));
    (new SelectionRepository())->set($catalog_ids, true, $user_id);

    $manual = new WC_Product_Simple(); $manual->set_name('Ręczny produkt'); $manual->set_status('draft'); $manual->set_sku($run . '-CONFLICT');
    $manual_id = (int) $manual->save(); $product_ids[] = $manual_id;
    $before_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('product','product_variation')");

    $preview_response = d_request('POST', '/import-preview', ['supplierId' => $supplier_id]);
    d_check($preview_response->get_status() === 201, 'dry-run endpoint creates an approval plan');
    $preview = $preview_response->get_data(); $job_id = (int) $preview['job']['id'];
    d_check($preview['job']['context']['summary']['create'] === 1 && $preview['job']['context']['summary']['conflict'] === 1, 'dry-run separates safe creation from unlinked SKU conflict');
    $after_preview_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('product','product_variation')");
    d_check($after_preview_count === $before_count, 'dry-run does not modify WooCommerce products');

    $missing_confirmation = d_request('POST', '/jobs/' . $job_id . '/approve', ['confirm' => false]);
    d_check($missing_confirmation->get_status() === 400, 'import approval requires explicit confirmation');
    $executed = (new ImportExecutor())->execute($job_id, $user_id);
    d_check($executed['job']['succeededItems'] === 1, 'confirmed execution imports only the ready item');
    $linked_id = (int) $wpdb->get_var($wpdb->prepare('SELECT wc_product_id FROM ' . Schema::table('product_links') . ' WHERE supplier_id=%d AND external_id=%s', $supplier_id, 'safe-1'));
    $product_ids[] = $linked_id; $imported = wc_get_product($linked_id);
    d_check($imported && $imported->get_status() === 'draft' && $imported->get_stock_quantity() === 7, 'new product is a draft with mapped stock');
    d_check((float) $imported->get_regular_price() === 120.0, 'pricing rule calculates the WooCommerce price');

    $second_preview = d_request('POST', '/import-preview', ['supplierId' => $supplier_id])->get_data();
    d_check($second_preview['job']['context']['summary']['update'] === 1 && $second_preview['job']['context']['summary']['conflict'] === 1, 'second dry-run recognizes supplier link as update');
    $second_execute = (new ImportExecutor())->execute((int) $second_preview['job']['id'], $user_id);
    d_check($second_execute['job']['status'] === 'completed', 'repeat import executes successfully');
    $linked_count = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . Schema::table('product_links') . ' WHERE supplier_id=%d AND external_id=%s', $supplier_id, 'safe-1'));
    d_check($linked_count === 1 && wc_get_product_id_by_sku($run . '-SAFE') === $linked_id, 'repeat import is idempotent and creates no duplicate');
    d_check(wc_get_product($manual_id)?->get_name() === 'Ręczny produkt', 'unlinked manually managed product is not overwritten');

    file_put_contents($temporary, wp_json_encode(['products' => [
        ['id' => 'safe-1', 'sku' => $run . '-SAFE', 'ean' => '5901234123457', 'name' => 'Bezpieczny produkt', 'price' => 110, 'tax' => 23, 'stock' => 4, 'category' => 'Legowiska'],
        ['id' => 'conflict-1', 'sku' => $run . '-CONFLICT', 'ean' => '', 'name' => 'Konflikt SKU', 'price' => 50, 'tax' => 23, 'stock' => 2, 'category' => 'Legowiska'],
    ]]));
    (new CatalogIngestService())->ingest(['supplierId'=>$supplier_id,'file'=>$temporary,'format'=>'json','recordPath'=>'products','declaredComplete'=>true,'mapping'=>['external_id'=>'id','sku'=>'sku','ean'=>'ean','name'=>'name','purchase_price'=>'price','tax_rate'=>'tax','stock'=>'stock','categories'=>'category']]);
    $sync = (new SyncManager())->enqueue($supplier_id, $user_id, ['price','stock']);
    (new JobQueue())->process_import_batch((int)$sync['job']['id'], $user_id);
    (new JobQueue())->cancel((int)$sync['job']['id']);
    $synced = wc_get_product($linked_id);
    d_check($synced->get_stock_quantity() === 4 && abs((float)$synced->get_regular_price()-132.0)<0.0001, 'manual synchronization updates only configured price and stock fields');
    echo $checks . ' Stage D integration checks passed.' . PHP_EOL;
} finally {
    global $wpdb;
    foreach (array_unique(array_filter($product_ids)) as $id) { wp_delete_post((int) $id, true); }
    if ($term_id > 0) { wp_delete_term($term_id, 'product_cat'); }
    if ($supplier_id > 0) {
        $catalog_ids = $wpdb->get_col($wpdb->prepare('SELECT id FROM ' . Schema::table('catalog_products') . ' WHERE supplier_id=%d', $supplier_id));
        foreach ($catalog_ids as $id) { $wpdb->delete(Schema::table('product_history'), ['catalog_product_id' => (int) $id], ['%d']); }
        $job_ids = $wpdb->get_col($wpdb->prepare('SELECT id FROM ' . Schema::table('jobs') . ' WHERE supplier_id=%d', $supplier_id));
        foreach ($job_ids as $id) { $wpdb->delete(Schema::table('job_items'), ['job_id' => (int) $id], ['%d']); }
        foreach (['product_links', 'category_mappings', 'product_selections', 'pricing_rules', 'jobs', 'supplier_offers', 'catalog_products', 'supplier_categories', 'supplier_sources', 'secrets', 'feed_runs'] as $suffix) { $wpdb->delete(Schema::table($suffix), ['supplier_id' => $supplier_id], ['%d']); }
        $wpdb->delete(Schema::table('suppliers'), ['id' => $supplier_id], ['%d']);
    }
    if ($temporary !== '' && file_exists($temporary)) { unlink($temporary); }
    if ($user_id > 0) { wp_delete_user($user_id); }
    wp_set_current_user(0); echo 'Stage D fixtures cleaned up.' . PHP_EOL;
}
