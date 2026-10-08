<?php
/** Catalog ingest integration checks. Creates and removes only tagged fixtures. */
if (PHP_SAPI !== 'cli') { exit; }
require '/var/www/html/wp-load.php';

use Pupilovo\SupplierHub\Application\CatalogIngestService;
use Pupilovo\SupplierHub\Infrastructure\Database\Schema;
use Pupilovo\SupplierHub\Infrastructure\Repository\SupplierRepository;

$checks = 0; $supplier_id = 0; $temporary = '';
function catalog_check($condition, string $label): void {
    if (!$condition) { throw new RuntimeException('FAIL: ' . $label); }
    ++$GLOBALS['checks']; echo 'PASS: ' . $label . PHP_EOL;
}

try {
    Schema::activate();
    global $wpdb;
    foreach (['supplier_sources', 'secrets', 'feed_runs', 'canonical_products', 'supplier_offers', 'product_history'] as $suffix) {
        $table = Schema::table($suffix);
        catalog_check($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table, 'schema v2 table exists: ' . $suffix);
    }

    $name = 'Catalog test ' . bin2hex(random_bytes(5));
    $supplier = (new SupplierRepository())->create([
        'name' => $name, 'sourceType' => 'file_xml', 'adapterKey' => '', 'status' => 'draft',
        'sourceConfig' => [], 'fieldMapping' => [],
    ], 0);
    $supplier_id = (int) $supplier['id'];
    $mapping = [
        'external_id' => '@id', 'sku' => 'identity.sku', 'ean' => 'identity.ean',
        'name' => 'name', 'purchase_price' => 'price.#text',
        'currency' => 'price.@currency', 'categories' => 'categories.category', 'images' => 'images.image',
    ];
    $base = [
        'supplierId' => $supplier_id,
        'file' => __DIR__ . '/fixtures/attributes-nested.xml',
        'format' => 'xml', 'recordPath' => 'catalog.products.product', 'mapping' => $mapping,
        'declaredComplete' => true,
    ];
    $service = new CatalogIngestService();
    $first = $service->ingest($base);
    catalog_check($first['created'] === 2 && $first['completeness'] === 'complete', 'first complete feed creates two catalog products');
    catalog_check((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . Schema::table('catalog_products') . ' WHERE supplier_id = %d', $supplier_id)) === 2, 'catalog products persisted');
    catalog_check((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . Schema::table('supplier_offers') . ' WHERE supplier_id = %d', $supplier_id)) === 2, 'supplier offers persisted separately');

    $second = $service->ingest($base);
    catalog_check($second['unchanged'] === 2 && $second['created'] === 0, 'repeated feed is idempotent');
    catalog_check((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . Schema::table('product_history') . ' h JOIN ' . Schema::table('catalog_products') . ' p ON p.id=h.catalog_product_id WHERE p.supplier_id=%d', $supplier_id)) === 2, 'unchanged feed does not duplicate history');

    $temporary = tempnam(sys_get_temp_dir(), 'psh-feed-');
    file_put_contents($temporary, '<?xml version="1.0"?><catalog><products><product id="p-1"><identity><sku>SKU-1</sku></identity><name>Legowisko</name><price currency="PLN">129.90</price></product></products></catalog>');
    $partial_command = [...$base, 'file' => $temporary, 'declaredComplete' => false];
    $partial = $service->ingest($partial_command);
    catalog_check($partial['completeness'] === 'partial' && $partial['markedMissing'] === 0, 'partial feed never marks absent offers');

    $suspicious = $service->ingest([...$partial_command, 'declaredComplete' => true]);
    catalog_check($suspicious['completeness'] === 'suspicious' && $suspicious['markedMissing'] === 0, 'unexpected count drop blocks mass availability changes');

    $accepted = $service->ingest([...$partial_command, 'declaredComplete' => true, 'minimumCompletenessRatio' => 0.5]);
    catalog_check($accepted['completeness'] === 'complete' && $accepted['markedMissing'] === 1, 'validated complete feed marks only absent supplier offer');
    $missing = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . Schema::table('supplier_offers') . " WHERE supplier_id=%d AND offer_status='missing'", $supplier_id));
    catalog_check($missing === 1, 'missing status is isolated to supplier offer');

    echo $checks . ' catalog integration checks passed.' . PHP_EOL;
} finally {
    global $wpdb;
    if ($supplier_id > 0) {
        $product_ids = $wpdb->get_col($wpdb->prepare('SELECT id FROM ' . Schema::table('catalog_products') . ' WHERE supplier_id=%d', $supplier_id));
        $run_ids = $wpdb->get_col($wpdb->prepare('SELECT id FROM ' . Schema::table('feed_runs') . ' WHERE supplier_id=%d', $supplier_id));
        foreach ($product_ids as $id) { $wpdb->delete(Schema::table('product_history'), ['catalog_product_id' => (int) $id], ['%d']); }
        foreach (['supplier_offers', 'catalog_products', 'supplier_sources', 'secrets', 'feed_runs', 'suppliers'] as $suffix) {
            $wpdb->delete(Schema::table($suffix), [$suffix === 'suppliers' ? 'id' : 'supplier_id' => $supplier_id], ['%d']);
        }
        foreach ($run_ids as $id) { $wpdb->delete(Schema::table('product_history'), ['feed_run_id' => (int) $id], ['%d']); }
    }
    if ($temporary !== '' && file_exists($temporary)) { unlink($temporary); }
    echo 'Catalog fixtures cleaned up.' . PHP_EOL;
}
