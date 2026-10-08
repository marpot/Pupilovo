<?php
/** Variable product and image safety integration checks. */
if (PHP_SAPI !== 'cli') { exit; }
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

use Pupilovo\SupplierHub\Application\CatalogIngestService;
use Pupilovo\SupplierHub\Application\ImportExecutor;
use Pupilovo\SupplierHub\Application\ImportPreviewService;
use Pupilovo\SupplierHub\Infrastructure\Database\Schema;
use Pupilovo\SupplierHub\Infrastructure\Repository\CategoryRepository;
use Pupilovo\SupplierHub\Infrastructure\Repository\PricingRuleRepository;
use Pupilovo\SupplierHub\Infrastructure\Repository\SelectionRepository;
use Pupilovo\SupplierHub\Infrastructure\Repository\SupplierRepository;

$checks = 0; $supplier_id = 0; $user_id = 0; $term_id = 0; $temporary = ''; $parent_id = 0;
function dv_check($condition, string $label): void { if (!$condition) { throw new RuntimeException('FAIL: ' . $label); } ++$GLOBALS['checks']; echo 'PASS: ' . $label . PHP_EOL; }

try {
    global $wpdb;
    $run = 'psh-dv-' . bin2hex(random_bytes(5));
    $user_id = wp_insert_user(['user_login' => $run, 'user_email' => $run . '@example.test', 'user_pass' => wp_generate_password(24), 'role' => 'administrator']);
    if (is_wp_error($user_id)) { throw new RuntimeException($user_id->get_error_message()); }
    wp_set_current_user($user_id);
    $supplier = (new SupplierRepository())->create(['name' => $run, 'sourceType' => 'file_json', 'adapterKey' => '', 'status' => 'draft', 'sourceConfig' => [], 'fieldMapping' => []], $user_id);
    $supplier_id = (int) $supplier['id'];
    $temporary = tempnam(sys_get_temp_dir(), 'psh-variants-');
    file_put_contents($temporary, wp_json_encode(['products' => [[
        'id' => 'variable-1', 'sku' => $run . '-PARENT', 'name' => 'Szelki wariantowe', 'price' => 80, 'tax' => 23, 'stock' => 10,
        'category' => 'Szelki', 'images' => ['http://127.0.0.1/blocked.jpg'],
        'attributes' => ['Kolor' => ['Czerwony', 'Niebieski']],
        'variants' => [
            ['id' => 'red', 'sku' => $run . '-RED', 'purchase_price' => 80, 'stock' => 3, 'attributes' => ['Kolor' => 'Czerwony']],
            ['id' => 'blue', 'sku' => $run . '-BLUE', 'purchase_price' => 90, 'stock' => 4, 'attributes' => ['Kolor' => 'Niebieski']],
        ],
    ]]]));
    (new CatalogIngestService())->ingest([
        'supplierId' => $supplier_id, 'file' => $temporary, 'format' => 'json', 'recordPath' => 'products', 'declaredComplete' => true,
        'mapping' => ['external_id' => 'id', 'sku' => 'sku', 'name' => 'name', 'purchase_price' => 'price', 'tax_rate' => 'tax', 'stock' => 'stock', 'categories' => 'category', 'images' => 'images', 'attributes' => 'attributes', 'variants' => 'variants'],
    ]);
    $term = wp_insert_term('Szelki ' . $run, 'product_cat'); if (is_wp_error($term)) { throw new RuntimeException($term->get_error_message()); } $term_id = (int) $term['term_id'];
    $category_id = (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . Schema::table('supplier_categories') . ' WHERE supplier_id=%d AND name=%s', $supplier_id, 'Szelki'));
    (new CategoryRepository())->save_mapping($category_id, $term_id, 'approved', 'manual', 1.0, $user_id);
    (new PricingRuleRepository())->save($supplier_id, ['scopeType' => 'supplier', 'priority' => 10, 'active' => true, 'config' => ['mode' => 'markup', 'value' => 25, 'purchasePriceIncludesTax' => false]]);
    $catalog_id = (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . Schema::table('catalog_products') . ' WHERE supplier_id=%d', $supplier_id));
    (new SelectionRepository())->set([$catalog_id], true, $user_id);

    $preview = (new ImportPreviewService())->create($supplier_id, $user_id);
    $executed = (new ImportExecutor())->execute((int) $preview['job']['id'], $user_id);
    dv_check($executed['job']['status'] === 'completed', 'variable product import completes');
    $parent_id = (int) $executed['items'][0]['wcProductId']; $parent = wc_get_product($parent_id);
    dv_check($parent instanceof WC_Product_Variable && $parent->get_status() === 'draft', 'variable parent is created as draft');
    $children = $parent->get_children();
    dv_check(count($children) === 2, 'two supplier variants are created');
    $prices = array_map(static fn($id): float => (float) wc_get_product($id)->get_regular_price(), $children); sort($prices);
    dv_check(abs($prices[0] - 100.0) < 0.0001 && abs($prices[1] - 112.504065) < 0.0001, 'variant-specific purchase prices use the approved pricing rule and gross rounding');
    dv_check(get_post_meta($parent_id, '_pupilovo_image_errors', true) === ['unsafe_source_scheme'], 'unsafe image URL is blocked and recorded without failing product import');

    $second = (new ImportPreviewService())->create($supplier_id, $user_id);
    (new ImportExecutor())->execute((int) $second['job']['id'], $user_id);
    dv_check(count(wc_get_product($parent_id)->get_children()) === 2, 'repeat import updates variants without duplicates');
    echo $checks . ' Stage D variable product checks passed.' . PHP_EOL;
} finally {
    global $wpdb;
    if ($parent_id > 0) { foreach (wc_get_product($parent_id)?->get_children() ?? [] as $id) { wp_delete_post((int) $id, true); } wp_delete_post($parent_id, true); }
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
    if ($user_id > 0) { wp_delete_user($user_id); } wp_set_current_user(0);
    echo 'Stage D variable fixtures cleaned up.' . PHP_EOL;
}
