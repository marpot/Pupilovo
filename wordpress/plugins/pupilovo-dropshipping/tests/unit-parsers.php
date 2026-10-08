<?php
/** Parser and normalization unit checks. Run inside the WordPress container. */
if (PHP_SAPI !== 'cli') { exit; }
require '/var/www/html/wp-load.php';

use Pupilovo\SupplierHub\Domain\Mapping\FieldPath;
use Pupilovo\SupplierHub\Domain\Product\ProductNormalizer;
use Pupilovo\SupplierHub\Infrastructure\Feed\FeedParserFactory;

$checks = 0;
function parser_check($condition, string $label): void {
    if (!$condition) { throw new RuntimeException('FAIL: ' . $label); }
    ++$GLOBALS['checks']; echo 'PASS: ' . $label . PHP_EOL;
}

$fixtures = __DIR__ . '/fixtures/';
$factory = new FeedParserFactory();

$csv = iterator_to_array($factory->create('csv', $fixtures . 'simple-products.csv', ['delimiter' => ';'])->records('', 10));
parser_check(count($csv) === 2 && $csv[0]['sku'] === 'DOG-BOWL-01', 'CSV records streamed');
$tsv = iterator_to_array($factory->create('tsv', $fixtures . 'simple-products.tsv')->records('', 10));
parser_check(count($tsv) === 2 && $tsv[1]['sku'] === 'TSV-2', 'TSV records streamed');

$xml_parser = $factory->create('xml', $fixtures . 'attributes-nested.xml');
$xml = iterator_to_array($xml_parser->records('catalog.products.product', 10));
parser_check(count($xml) === 2, 'nested XML record path streamed');
$paths = new FieldPath();
parser_check($paths->get($xml[0], '@id') === 'p-1', 'XML attribute mapped');
parser_check($paths->get($xml[0], 'identity.sku') === 'SKU-1', 'nested XML element mapped');
parser_check($paths->get($xml[0], 'categories.category') === ['Psy', 'Legowiska'], 'repeated XML elements mapped as list');

$json_parser = $factory->create('json', $fixtures . 'nested-products.json');
$json = iterator_to_array($json_parser->records('data.products', 10));
parser_check(count($json) === 2 && $json[1]['identity']['sku'] === 'JSON-2', 'nested JSON array streamed');
$inspection = $json_parser->inspect('data.products', 1);
parser_check(in_array('pricing.net', $inspection['fields'], true) && count($inspection['samples']) === 1, 'feed inspection discovers nested fields');

$normalizer = new ProductNormalizer();
$mapped = $normalizer->normalize($json[0], [
    'external_id' => 'id', 'sku' => 'identity.sku', 'ean' => 'identity.ean',
    'name' => 'title', 'purchase_price' => 'pricing.net', 'categories' => 'categories',
    'images' => 'images.url',
]);
parser_check($mapped['errors'] === [] && $mapped['product']['purchase_price'] === 41.25, 'mapped JSON product validates');
parser_check($mapped['product']['images'] === ['https://cdn.example.test/a.jpg'], 'list path maps nested image URLs');
parser_check($normalizer->valid_gtin('5901234123457'), 'valid GTIN accepted');
parser_check(!$normalizer->valid_gtin('5901234123458'), 'invalid GTIN rejected');

$missing = $normalizer->normalize(['id' => 'only-id'], ['external_id' => 'id', 'name' => 'name']);
parser_check(in_array('missing_name', $missing['errors'], true), 'missing required field rejected');

try {
    iterator_to_array($factory->create('json', $fixtures . 'malformed.json')->records('products'));
    parser_check(false, 'malformed JSON rejected');
} catch (Throwable) {
    parser_check(true, 'malformed JSON rejected');
}

echo $checks . ' parser unit checks passed.' . PHP_EOL;
