<?php

namespace Pupilovo\SupplierHub\Infrastructure\Repository;

use Pupilovo\SupplierHub\Infrastructure\Database\Schema;

defined('ABSPATH') || exit;

final class SelectionRepository {
    public function set(array $product_ids, bool $selected, int $user_id): int {
        global $wpdb;
        $product_ids = array_values(array_unique(array_filter(array_map('intval', $product_ids), static fn(int $id): bool => $id > 0)));
        if (count($product_ids) > 1000) { throw new \InvalidArgumentException('Jedna operacja może zawierać maksymalnie 1000 identyfikatorów.'); }
        if ($product_ids === []) { return 0; }
        $catalog = Schema::table('catalog_products'); $table = Schema::table('product_selections');
        $changed = 0;
        foreach (array_chunk($product_ids, 200) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '%d'));
            if ($selected) {
                $sql = "INSERT IGNORE INTO {$table} (supplier_id,catalog_product_id,selected_by,selected_at)
                        SELECT supplier_id,id,%d,%s FROM {$catalog} WHERE id IN ({$placeholders}) AND record_status='valid'";
                $result = $wpdb->query($wpdb->prepare($sql, $user_id, current_time('mysql', true), ...$chunk));
            } else {
                $result = $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE catalog_product_id IN ({$placeholders})", ...$chunk));
            }
            if ($result !== false) { $changed += (int) $result; }
        }

        return $changed;
    }

    public function set_by_filter(array $filter, bool $selected, int $user_id): int {
        global $wpdb;
        $catalog = Schema::table('catalog_products'); $table = Schema::table('product_selections');
        $where = ["p.record_status='valid'"]; $values = [];
        if (!empty($filter['supplierId'])) { $where[] = 'p.supplier_id=%d'; $values[] = (int) $filter['supplierId']; }
        if (!empty($filter['category'])) { $where[] = 'p.supplier_category_path LIKE %s'; $values[] = '%' . $wpdb->esc_like((string) $filter['category']) . '%'; }
        if (!empty($filter['availability'])) { $where[] = 'p.availability=%s'; $values[] = (string) $filter['availability']; }
        if (!empty($filter['search'])) { $like = '%' . $wpdb->esc_like((string) $filter['search']) . '%'; $where[] = '(p.name LIKE %s OR p.sku LIKE %s OR p.ean LIKE %s)'; array_push($values, $like, $like, $like); }
        $where_sql = implode(' AND ', $where);
        if ($selected) {
            $sql = "INSERT IGNORE INTO {$table} (supplier_id,catalog_product_id,selected_by,selected_at) SELECT p.supplier_id,p.id,%d,%s FROM {$catalog} p WHERE {$where_sql}";
            $params = [$user_id, current_time('mysql', true), ...$values];
        } else {
            $sql = "DELETE sel FROM {$table} sel JOIN {$catalog} p ON p.id=sel.catalog_product_id WHERE {$where_sql}";
            $params = $values;
        }
        $result = $wpdb->query($params ? $wpdb->prepare($sql, ...$params) : $sql);

        return $result === false ? 0 : (int) $result;
    }

    public function count(): int {
        global $wpdb;
        return (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . Schema::table('product_selections'));
    }
}
