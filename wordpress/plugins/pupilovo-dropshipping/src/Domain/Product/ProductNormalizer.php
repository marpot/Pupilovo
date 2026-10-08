<?php

namespace Pupilovo\SupplierHub\Domain\Product;

use Pupilovo\SupplierHub\Domain\Mapping\FieldPath;

defined('ABSPATH') || exit;

final class ProductNormalizer {
    public function __construct(private readonly FieldPath $paths = new FieldPath()) {}

    /** @return array{product:array<string,mixed>,errors:array<int,string>,warnings:array<int,string>} */
    public function normalize(array $record, array $mapping, string $default_currency = 'PLN'): array {
        $value = fn(string $key) => isset($mapping[$key]) ? $this->paths->get($record, (string) $mapping[$key]) : null;
        $product = [
            'external_id' => $this->scalar($value('external_id')),
            'sku' => $this->scalar($value('sku')),
            'ean' => preg_replace('/\D+/', '', $this->scalar($value('ean'))),
            'name' => $this->scalar($value('name')),
            'description' => $this->scalar($value('description')),
            'short_description' => $this->scalar($value('short_description')),
            'purchase_price' => $this->decimal($value('purchase_price')),
            'currency' => strtoupper($this->scalar($value('currency')) ?: $default_currency),
            'tax_rate' => $this->decimal($value('tax_rate')),
            'stock_quantity' => $this->decimal($value('stock')),
            'availability' => strtolower($this->scalar($value('availability'))),
            'categories' => $this->list($value('categories')),
            'brand' => $this->scalar($value('brand')),
            'images' => $this->list($value('images')),
            'attributes' => $this->associative($value('attributes')),
            'variants' => $this->records($value('variants')),
            'weight' => $this->decimal($value('weight')),
            'dimensions' => $this->associative($value('dimensions')),
        ];
        $errors = [];
        $warnings = [];
        if ($product['external_id'] === '') { $errors[] = 'missing_external_id'; }
        if ($product['name'] === '') { $errors[] = 'missing_name'; }
        if ($product['purchase_price'] !== null && $product['purchase_price'] < 0) { $errors[] = 'negative_purchase_price'; }
        if ($product['stock_quantity'] !== null && $product['stock_quantity'] < 0) { $warnings[] = 'negative_stock_normalized_later'; }
        if ($product['ean'] !== '' && !$this->valid_gtin($product['ean'])) { $warnings[] = 'invalid_gtin'; }
        if (!preg_match('/^[A-Z]{3}$/', $product['currency'])) { $errors[] = 'invalid_currency'; }

        return ['product' => $product, 'errors' => $errors, 'warnings' => $warnings];
    }

    public function valid_gtin(string $value): bool {
        if (!in_array(strlen($value), [8, 12, 13, 14], true) || !ctype_digit($value)) {
            return false;
        }
        $sum = 0;
        $digits = str_split($value);
        $check = (int) array_pop($digits);
        $reverse = array_reverse($digits);
        foreach ($reverse as $index => $digit) {
            $sum += (int) $digit * ($index % 2 === 0 ? 3 : 1);
        }

        return (10 - ($sum % 10)) % 10 === $check;
    }

    private function scalar($value): string {
        if (is_bool($value)) { return $value ? '1' : '0'; }
        if (!is_scalar($value)) { return ''; }

        return trim(wp_strip_all_tags((string) $value));
    }

    private function decimal($value): ?float {
        if ($value === null || $value === '') { return null; }
        $normalized = str_replace([' ', ','], ['', '.'], $this->scalar($value));

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function list($value): array {
        if ($value === null || $value === '') { return []; }
        if (!is_array($value)) { $value = preg_split('/\s*[|;,]\s*/', (string) $value) ?: []; }
        $flat = [];
        array_walk_recursive($value, static function ($item) use (&$flat): void {
            if (is_scalar($item) && trim((string) $item) !== '') { $flat[] = trim((string) $item); }
        });

        return array_values(array_unique($flat));
    }

    private function associative($value): array {
        return is_array($value) ? $value : [];
    }

    private function records($value): array {
        return is_array($value) ? (array_is_list($value) ? $value : [$value]) : [];
    }
}
