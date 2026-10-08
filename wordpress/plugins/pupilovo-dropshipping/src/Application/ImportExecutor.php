<?php

namespace Pupilovo\SupplierHub\Application;

use Pupilovo\SupplierHub\Infrastructure\Database\Schema;
use Pupilovo\SupplierHub\Infrastructure\Repository\ImportJobRepository;
use Pupilovo\SupplierHub\Infrastructure\Repository\PricingRuleRepository;
use Pupilovo\SupplierHub\Domain\Pricing\PricingCalculator;

defined('ABSPATH') || exit;

final class ImportExecutor {
    public function __construct(
        private readonly ImportJobRepository $jobs = new ImportJobRepository(),
        private readonly PricingRuleRepository $rules = new PricingRuleRepository(),
        private readonly PricingCalculator $pricing = new PricingCalculator(),
        private readonly ProductImageImporter $images = new ProductImageImporter()
    ) {}

    /** @return array<string,mixed> */
    public function execute(int $job_id, int $user_id): array {
        do {
            $result = $this->process_batch($job_id, $user_id, 25);
        } while ($result['hasMore']);
        return ['job' => $this->jobs->get($job_id), 'items' => $this->jobs->items($job_id)];
    }

    /** @return array{job:array<string,mixed>,hasMore:bool,processedInBatch:int,nextDelay:int} */
    public function process_batch(int $job_id, int $user_id, int $batch_size = 10): array {
        $job = $this->jobs->get($job_id);
        if (!$job || !in_array($job['type'], ['import', 'sync'], true)) {
            throw new \InvalidArgumentException('Nie znaleziono planu importu.');
        }
        if (!in_array($job['status'], ['awaiting_approval', 'queued', 'running'], true)) {
            throw new \RuntimeException('Plan nie oczekuje na zatwierdzenie.');
        }
        if ($job['status'] !== 'running') {
            $this->assert_pricing_snapshot($job['context']);
            $this->jobs->update_job($job_id, ['status'=>'running','dry_run'=>0,'requested_by'=>$user_id,'started_at'=>current_time('mysql',true),'finished_at'=>null,'heartbeat_at'=>current_time('mysql',true)]);
        }

        $processed = (int) $job['processedItems']; $succeeded = (int) $job['succeededItems']; $failed = (int) $job['failedItems']; $batch_count = 0;
        foreach ($this->jobs->ready_items($job_id, $batch_size) as $item) {
            ++$processed; ++$batch_count;
            try {
                $catalog = $this->current_catalog($item);
                $wc_product_id = $this->import_item($item, $catalog);
                $this->link($catalog, $wc_product_id);
                $this->jobs->update_item($item['id'], [
                    'status' => 'completed', 'wc_product_id' => $wc_product_id,
                    'attempts' => $item['attempts']+1, 'result_summary' => wp_json_encode(['wcProductId' => $wc_product_id]),
                    'error_code' => null,
                ]);
                ++$succeeded;
            } catch (\Throwable $error) {
                $this->jobs->update_item($item['id'], [
                    'status' => 'failed', 'attempts' => $item['attempts']+1,
                    'error_code' => $this->error_code($error),
                    'result_summary' => wp_json_encode(['message' => sanitize_text_field($error->getMessage())]),
                ]);
                ++$failed;
            }
            $this->jobs->update_job($job_id, [
                'processed_items' => $processed, 'succeeded_items' => $succeeded,
                'failed_items' => $failed, 'heartbeat_at' => current_time('mysql', true),
            ]);
        }
        $retry=['count'=>0,'delay'=>0];if($this->jobs->count_ready($job_id)===0){$retry=$this->jobs->requeue_failed($job_id,3);}
        $has_more = $this->jobs->count_ready($job_id) > 0;$counts=$this->jobs->status_counts($job_id);$succeeded=$counts['completed']??0;$failed=$counts['failed']??0;$processed=$succeeded+$failed;
        $status = $has_more ? 'running' : ($failed === 0 ? 'completed' : ($succeeded > 0 ? 'completed_with_errors' : 'failed'));
        $final = ['status'=>$status,'processed_items'=>$processed,'succeeded_items'=>$succeeded,'failed_items'=>$failed,'heartbeat_at'=>current_time('mysql',true)];
        if (!$has_more) { $final['finished_at'] = current_time('mysql', true); }
        $this->jobs->update_job($job_id, $final);
        return ['job'=>$this->jobs->get($job_id),'hasMore'=>$has_more,'processedInBatch'=>$batch_count,'nextDelay'=>(int)$retry['delay']];
    }

    private function assert_pricing_snapshot(array $context): void {
        foreach (($context['pricingRuleHashes'] ?? []) as $supplier_id => $expected) {
            if (!hash_equals((string) $expected, $this->rules->hash_for_supplier((int) $supplier_id))) {
                throw new \RuntimeException('Reguły cenowe zmieniły się. Utwórz nowy dry-run.');
            }
        }
    }

    private function current_catalog(array $item): array {
        global $wpdb;
        $expected = $item['after'];
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . Schema::table('catalog_products') . ' WHERE id=%d',
            $item['catalogProductId']
        ), ARRAY_A);
        if (!$row || (int) $row['version'] !== (int) ($expected['catalogVersion'] ?? -1)
            || !hash_equals((string) ($expected['catalogChecksum'] ?? ''), (string) $row['checksum'])) {
            throw new \RuntimeException('Produkt katalogowy zmienił się. Utwórz nowy dry-run.');
        }
        if (($item['action'] ?? '') === 'update' && ($item['after']['managedFields'] ?? []) !== []) {
            $active = (int) $wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(*) FROM ' . Schema::table('supplier_offers') . " WHERE supplier_id=%d AND catalog_product_id=%d AND offer_status='active'",
                (int) $row['supplier_id'], (int) $row['id']
            ));
            if ($active === 0) {
                throw new \RuntimeException('Oferta dostawcy nie jest już aktywna. Utwórz nowy dry-run.');
            }
        }
        return $row;
    }

    private function import_item(array $item, array $catalog): int {
        $after = $item['after']; $payload = $after['product'] ?? [];
        $has_variants = is_array($payload['variants'] ?? null) && $payload['variants'] !== [];
        if ($item['action'] === 'update') {
            $existing = wc_get_product((int) ($after['match']['wcProductId'] ?? 0));
            if (!$existing) { throw new \RuntimeException('Powiązany produkt WooCommerce nie istnieje.'); }
            $product = $has_variants ? new \WC_Product_Variable($existing->get_id()) : $existing;
            $expected_modified = $item['before']['modifiedGmt'] ?? null;
            $current_modified = $product->get_date_modified()?->date('c');
            if ($expected_modified && $current_modified !== $expected_modified) {
                throw new \RuntimeException('Produkt WooCommerce został ręcznie zmieniony po dry-run.');
            }
        } elseif ($item['action'] === 'create') {
            $product = $has_variants ? new \WC_Product_Variable() : new \WC_Product_Simple();
            $product->set_status('draft');
        } else {
            throw new \RuntimeException('Pozycja wymaga ręcznego rozstrzygnięcia.');
        }

        $managed = array_fill_keys($after['managedFields'] ?? [], true);
        if (isset($managed['name'])) { $product->set_name((string) ($payload['name'] ?? $catalog['name'])); }
        if (isset($managed['description'])) { $product->set_description(wp_kses_post((string) ($payload['description'] ?? ''))); }
        if (isset($managed['short_description'])) { $product->set_short_description(wp_kses_post((string) ($payload['short_description'] ?? ''))); }
        if ($product->get_id() === 0 && !empty($payload['sku'])) { $product->set_sku((string) $payload['sku']); }
        if (isset($managed['price'])) {
            $pricing = $after['pricing'] ?? [];
            if (($pricing['status'] ?? '') !== 'calculated') { throw new \RuntimeException('Cena wymaga decyzji administratora.'); }
            $price = wc_prices_include_tax() ? $pricing['salePriceGross'] : $pricing['salePriceNet'];
            $product->set_regular_price(wc_format_decimal($price));
        }
        if (isset($managed['stock'])) {
            if (($payload['stock_quantity'] ?? null) !== null) {
                $product->set_manage_stock(true);
                $product->set_stock_quantity(max(0, (float) $payload['stock_quantity']));
                $product->set_stock_status((float) $payload['stock_quantity'] > 0 ? 'instock' : 'outofstock');
            } else {
                $product->set_manage_stock(false);
                $product->set_stock_status(($payload['availability'] ?? '') === 'available' ? 'instock' : 'outofstock');
            }
        }
        if (isset($managed['categories'])) { $product->set_category_ids(array_map('intval', $after['categoryTermIds'] ?? [])); }
        if (isset($managed['weight']) && ($payload['weight'] ?? null) !== null) { $product->set_weight(wc_format_decimal($payload['weight'])); }
        if (isset($managed['dimensions'])) {
            $dimensions = is_array($payload['dimensions'] ?? null) ? $payload['dimensions'] : [];
            if (isset($dimensions['length'])) { $product->set_length(wc_format_decimal($dimensions['length'])); }
            if (isset($dimensions['width'])) { $product->set_width(wc_format_decimal($dimensions['width'])); }
            if (isset($dimensions['height'])) { $product->set_height(wc_format_decimal($dimensions['height'])); }
        }
        if (isset($managed['attributes'])) {
            $attribute_values = $payload['attributes'] ?? [];
            if ($has_variants) { $attribute_values = $this->variant_attribute_values($payload['variants'], $attribute_values); }
            $product->set_attributes($this->attributes($attribute_values, $has_variants));
        }
        if (!empty($payload['ean'])) {
            method_exists($product, 'set_global_unique_id')
                ? $product->set_global_unique_id((string) $payload['ean'])
                : $product->update_meta_data('_ean', (string) $payload['ean']);
        }
        $product->update_meta_data('_pupilovo_supplier_id', (int) $catalog['supplier_id']);
        $product->update_meta_data('_pupilovo_supplier_external_id', (string) $catalog['external_id']);
        $product->update_meta_data('_pupilovo_catalog_version', (int) $catalog['version']);
        $product_id = $product->save();
        if (!$product_id) { throw new \RuntimeException('WooCommerce nie zapisał produktu.'); }
        if ($has_variants) { $this->save_variations($product, $payload['variants'], $after, $catalog); }
        if (isset($managed['images']) && !empty($payload['images'])) {
            $images = $this->images->import($payload['images'], (int) $product_id);
            if ($images['ids'] !== []) {
                $product->set_image_id($images['ids'][0]);
                $product->set_gallery_image_ids(array_slice($images['ids'], 1));
                $product->save();
            }
            if ($images['errors'] !== []) { $product->update_meta_data('_pupilovo_image_errors', $images['errors']); $product->save_meta_data(); }
        }
        return (int) $product_id;
    }

    /** @return array<int,\WC_Product_Attribute> */
    private function attributes($values, bool $variation = false): array {
        if (!is_array($values)) { return []; }
        $attributes = []; $position = 0;
        foreach ($values as $name => $options) {
            if (!is_scalar($name) || trim((string) $name) === '') { continue; }
            $options = is_array($options) ? $options : [$options];
            $options = array_values(array_filter(array_map(static fn($value): string => sanitize_text_field((string) $value), $options)));
            if ($options === []) { continue; }
            $attribute = new \WC_Product_Attribute();
            $attribute->set_name(sanitize_text_field((string) $name));
            $attribute->set_options($options); $attribute->set_position($position++);
            $attribute->set_visible(true); $attribute->set_variation($variation);
            $attributes[] = $attribute;
        }
        return $attributes;
    }

    private function variant_attribute_values(array $variants, $base): array {
        $values = is_array($base) ? $base : [];
        foreach ($variants as $variant) {
            if (!is_array($variant) || !is_array($variant['attributes'] ?? null)) { continue; }
            foreach ($variant['attributes'] as $name => $value) {
                $values[$name] = array_values(array_unique(array_merge((array) ($values[$name] ?? []), (array) $value)));
            }
        }
        return $values;
    }

    private function save_variations(\WC_Product_Variable $parent, array $variants, array $after, array $catalog): void {
        $seen = [];
        foreach ($variants as $index => $data) {
            if (!is_array($data)) { continue; }
            $variant_key = sanitize_text_field((string) ($data['external_id'] ?? $data['id'] ?? ''));
            if ($variant_key === '') { $variant_key = hash('sha256', wp_json_encode($data['attributes'] ?? []) . ':' . $index); }
            $existing_ids = get_posts(['post_type' => 'product_variation', 'post_parent' => $parent->get_id(), 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 1, 'meta_key' => '_pupilovo_supplier_variant_id', 'meta_value' => $variant_key]);
            $variation = $existing_ids === [] ? new \WC_Product_Variation() : new \WC_Product_Variation((int) $existing_ids[0]);
            $variation->set_parent_id($parent->get_id()); $variation->set_status('publish');
            if ($variation->get_id() === 0 && !empty($data['sku'])) { $variation->set_sku(sanitize_text_field((string) $data['sku'])); }
            $attributes = [];
            foreach (($data['attributes'] ?? []) as $name => $value) { $attributes[sanitize_title((string) $name)] = sanitize_text_field((string) (is_array($value) ? reset($value) : $value)); }
            $variation->set_attributes($attributes);
            $price = $after['pricing'];
            if (isset($data['purchase_price']) && is_array($after['pricingRule']['config'] ?? null)) {
                $price = $this->pricing->calculate(['purchasePrice' => $data['purchase_price'], 'currency' => $data['currency'] ?? $catalog['currency'], 'taxRate' => $data['tax_rate'] ?? $catalog['tax_rate'], 'priceIncludesTax' => !empty($after['pricingRule']['config']['purchasePriceIncludesTax'])], $after['pricingRule']['config'], get_woocommerce_currency());
            }
            if (($price['status'] ?? '') !== 'calculated') { throw new \RuntimeException('Cena wariantu wymaga decyzji administratora.'); }
            $variation->set_regular_price(wc_format_decimal(wc_prices_include_tax() ? $price['salePriceGross'] : $price['salePriceNet']));
            if (array_key_exists('stock_quantity', $data) || array_key_exists('stock', $data)) {
                $stock = max(0, (float) ($data['stock_quantity'] ?? $data['stock']));
                $variation->set_manage_stock(true); $variation->set_stock_quantity($stock); $variation->set_stock_status($stock > 0 ? 'instock' : 'outofstock');
            }
            $variation->update_meta_data('_pupilovo_supplier_variant_id', $variant_key);
            $variation->update_meta_data('_pupilovo_supplier_id', (int) $catalog['supplier_id']);
            $variation_id = $variation->save(); $seen[] = (int) $variation_id;
        }
        \WC_Product_Variable::sync($parent, true);
        $parent->update_meta_data('_pupilovo_active_variation_ids', $seen); $parent->save_meta_data();
    }

    private function link(array $catalog, int $wc_product_id): void {
        global $wpdb;
        $table = Schema::table('product_links'); $now = current_time('mysql', true);
        $sql = "INSERT INTO {$table} (supplier_id,catalog_product_id,external_id,wc_product_id,relationship_status,is_primary,created_at,updated_at)
                VALUES (%d,%d,%s,%d,'linked',0,%s,%s)
                ON DUPLICATE KEY UPDATE catalog_product_id=VALUES(catalog_product_id),wc_product_id=VALUES(wc_product_id),relationship_status='linked',updated_at=VALUES(updated_at)";
        $result = $wpdb->query($wpdb->prepare($sql, $catalog['supplier_id'], $catalog['id'], $catalog['external_id'], $wc_product_id, $now, $now));
        if ($result === false) { throw new \RuntimeException('Nie udało się zapisać powiązania produktu.'); }
    }

    private function error_code(\Throwable $error): string {
        $message = strtolower($error->getMessage());
        if (str_contains($message, 'dry-run') || str_contains($message, 'zmieni')) { return 'stale_plan'; }
        if (str_contains($message, 'ręcznie')) { return 'manual_change_conflict'; }
        return 'import_failed';
    }

}
