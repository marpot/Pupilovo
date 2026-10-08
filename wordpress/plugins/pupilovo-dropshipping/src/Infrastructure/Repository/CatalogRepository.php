<?php

namespace Pupilovo\SupplierHub\Infrastructure\Repository;

use Pupilovo\SupplierHub\Infrastructure\Database\Schema;

defined('ABSPATH') || exit;

final class CatalogRepository {
    /** @return array{action:string,id:int,changedFields:array<int,string>} */
    public function upsert(int $supplier_id, int $feed_run_id, array $product, array $raw): array {
        global $wpdb;

        $catalog = Schema::table('catalog_products');
        $existing = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$catalog} WHERE supplier_id = %d AND external_id = %s", $supplier_id, $product['external_id']),
            ARRAY_A
        );
        $normalized_json = wp_json_encode($product, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $checksum = hash('sha256', (string) $normalized_json);
        $raw_json = wp_json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($raw_json) || strlen($raw_json) > 1048576) {
            $raw_json = wp_json_encode(['omitted' => true, 'reason' => 'record_too_large']);
        }
        $now = current_time('mysql', true);
        $availability = $product['availability'] ?: ($product['stock_quantity'] === null ? 'unknown' : ($product['stock_quantity'] > 0 ? 'available' : 'unavailable'));
        $categories = implode(' > ', $product['categories']);
        $image = $product['images'][0] ?? null;
        $data = [
            'sku' => $product['sku'] ?: null,
            'ean' => $product['ean'] ?: null,
            'name' => $product['name'],
            'description' => $product['description'] ?: null,
            'short_description' => $product['short_description'] ?: null,
            'purchase_price' => $product['purchase_price'],
            'currency' => $product['currency'],
            'tax_rate' => $product['tax_rate'],
            'stock_quantity' => $product['stock_quantity'],
            'availability' => $availability,
            'supplier_category_path' => $categories ?: null,
            'primary_image_url' => $image,
            'normalized_payload' => $normalized_json,
            'raw_payload' => $raw_json,
            'checksum' => $checksum,
            'feed_run_id' => $feed_run_id,
            'record_status' => 'valid',
            'validation_errors' => null,
            'last_seen_at' => $now,
            'updated_at' => $now,
        ];

        if (!$existing) {
            $inserted = $wpdb->insert(
                $catalog,
                ['supplier_id' => $supplier_id, 'external_id' => $product['external_id'], 'first_seen_at' => $now, ...$data],
                [
                    '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
                    '%f', '%s', '%f', '%f', '%s', '%s', '%s', '%s',
                    '%s', '%s', '%d', '%s', '%s', '%s', '%s',
                ]
            );
            if ($inserted !== 1) {
                throw new \RuntimeException('Nie udało się zapisać produktu katalogowego.');
            }
            $id = (int) $wpdb->insert_id;
            $this->history($id, $feed_run_id, 'created', [], null, $checksum);
            $this->upsert_offer($supplier_id, $id, $feed_run_id, $product, $availability, $now);
            (new CategoryRepository())->upsert_paths($supplier_id, $product['categories']);

            return ['action' => 'created', 'id' => $id, 'changedFields' => array_keys($product)];
        }

        $id = (int) $existing['id'];
        $changed = $this->changed_fields((string) $existing['normalized_payload'], $product);
        if (!hash_equals((string) $existing['checksum'], $checksum)) {
            $data['version'] = (int) $existing['version'] + 1;
            $updated = $wpdb->update($catalog, $data, ['id' => $id], null, ['%d']);
            if ($updated === false) {
                throw new \RuntimeException('Nie udało się zaktualizować produktu katalogowego.');
            }
            $this->history($id, $feed_run_id, 'updated', $changed, (string) $existing['checksum'], $checksum);
            $action = 'updated';
        } else {
            $wpdb->update(
                $catalog,
                ['feed_run_id' => $feed_run_id, 'last_seen_at' => $now, 'updated_at' => $now],
                ['id' => $id],
                ['%d', '%s', '%s'],
                ['%d']
            );
            $action = 'unchanged';
        }
        $this->upsert_offer($supplier_id, $id, $feed_run_id, $product, $availability, $now);
        (new CategoryRepository())->upsert_paths($supplier_id, $product['categories']);

        return ['action' => $action, 'id' => $id, 'changedFields' => $changed];
    }

    public function mark_missing_after_complete_run(int $supplier_id, int $feed_run_id): int {
        global $wpdb;

        $offers = Schema::table('supplier_offers');
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$offers} SET offer_status = 'missing', availability = 'unavailable', updated_at = %s
             WHERE supplier_id = %d AND (last_feed_run_id IS NULL OR last_feed_run_id <> %d) AND offer_status <> 'missing'",
            current_time('mysql', true),
            $supplier_id,
            $feed_run_id
        ));

        return $result === false ? 0 : (int) $result;
    }

    private function upsert_offer(int $supplier_id, int $catalog_id, int $feed_run_id, array $product, string $availability, string $now): void {
        global $wpdb;

        $table = Schema::table('supplier_offers');
        $sql = "INSERT INTO {$table}
            (supplier_id,catalog_product_id,external_id,supplier_sku,purchase_price,currency,stock_quantity,availability,offer_status,last_feed_run_id,first_seen_at,last_seen_at,updated_at)
            VALUES (%d,%d,%s,%s,NULLIF(%s,''),%s,NULLIF(%s,''),%s,'active',%d,%s,%s,%s)
            ON DUPLICATE KEY UPDATE supplier_sku=VALUES(supplier_sku),purchase_price=VALUES(purchase_price),currency=VALUES(currency),stock_quantity=VALUES(stock_quantity),availability=VALUES(availability),offer_status='active',last_feed_run_id=VALUES(last_feed_run_id),last_seen_at=VALUES(last_seen_at),updated_at=VALUES(updated_at)";
        $result = $wpdb->query($wpdb->prepare(
            $sql,
            $supplier_id,
            $catalog_id,
            $product['external_id'],
            $product['sku'] ?: '',
            $product['purchase_price'] === null ? '' : (string) $product['purchase_price'],
            $product['currency'],
            $product['stock_quantity'] === null ? '' : (string) $product['stock_quantity'],
            $availability,
            $feed_run_id,
            $now,
            $now,
            $now
        ));
        if ($result === false) {
            throw new \RuntimeException('Nie udało się zapisać oferty dostawcy.');
        }
    }

    private function history(int $product_id, int $run_id, string $type, array $fields, ?string $previous, string $current): void {
        global $wpdb;
        $wpdb->insert(Schema::table('product_history'), [
            'catalog_product_id' => $product_id,
            'feed_run_id' => $run_id,
            'change_type' => $type,
            'changed_fields' => wp_json_encode($fields),
            'previous_checksum' => $previous,
            'current_checksum' => $current,
            'created_at' => current_time('mysql', true),
        ], ['%d', '%d', '%s', '%s', '%s', '%s', '%s']);
    }

    private function changed_fields(string $previous_json, array $current): array {
        $previous = json_decode($previous_json, true);
        if (!is_array($previous)) { return array_keys($current); }
        $changed = [];
        foreach ($current as $key => $value) {
            if (!array_key_exists($key, $previous) || $previous[$key] !== $value) { $changed[] = $key; }
        }

        return $changed;
    }
}
