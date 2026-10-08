<?php

namespace Pupilovo\SupplierHub\Infrastructure\Repository;

use Pupilovo\SupplierHub\Infrastructure\Database\Schema;

defined('ABSPATH') || exit;

final class CatalogQueryRepository {
    public function paginate(array $query): array {
        global $wpdb;

        $table = Schema::table('catalog_products');
        $suppliers = Schema::table('suppliers');
        $selections = Schema::table('product_selections');
        $links = Schema::table('product_links');
        $page = max(1, (int) ($query['page'] ?? 1));
        $per_page = min(100, max(1, (int) ($query['perPage'] ?? 25)));
        $where = ["p.record_status = 'valid'", "s.status <> 'deleted'"];
        $values = [];
        if (!empty($query['supplierId'])) { $where[] = 'p.supplier_id = %d'; $values[] = (int) $query['supplierId']; }
        if (!empty($query['search'])) {
            $like = '%' . $wpdb->esc_like((string) $query['search']) . '%';
            $where[] = '(p.name LIKE %s OR p.sku LIKE %s OR p.ean LIKE %s)';
            array_push($values, $like, $like, $like);
        }
        if (!empty($query['availability'])) { $where[] = 'p.availability = %s'; $values[] = (string) $query['availability']; }
        if (isset($query['minPrice']) && $query['minPrice'] !== '') { $where[] = 'p.purchase_price >= %f'; $values[] = (float) $query['minPrice']; }
        if (isset($query['maxPrice']) && $query['maxPrice'] !== '') { $where[] = 'p.purchase_price <= %f'; $values[] = (float) $query['maxPrice']; }
        if (($query['selected'] ?? '') === 'yes') { $where[] = 'sel.id IS NOT NULL'; }
        if (($query['selected'] ?? '') === 'no') { $where[] = 'sel.id IS NULL'; }
        $sorts = ['updated' => 'p.updated_at', 'name' => 'p.name', 'price' => 'p.purchase_price', 'stock' => 'p.stock_quantity'];
        $sort = $sorts[$query['sort'] ?? 'updated'] ?? $sorts['updated'];
        $direction = strtolower((string) ($query['direction'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $where_sql = implode(' AND ', $where);
        $joins = "JOIN {$suppliers} s ON s.id=p.supplier_id LEFT JOIN {$selections} sel ON sel.catalog_product_id=p.id LEFT JOIN {$links} l ON l.catalog_product_id=p.id";
        $count_sql = "SELECT COUNT(DISTINCT p.id) FROM {$table} p {$joins} WHERE {$where_sql}";
        $total = (int) $wpdb->get_var($values ? $wpdb->prepare($count_sql, ...$values) : $count_sql);
        $sql = "SELECT p.id,p.supplier_id,p.external_id,p.sku,p.ean,p.name,p.purchase_price,p.currency,p.tax_rate,p.stock_quantity,p.availability,p.supplier_category_path,p.primary_image_url,p.version,p.updated_at,s.name supplier_name,IF(sel.id IS NULL,0,1) selected,l.wc_product_id
                FROM {$table} p {$joins} WHERE {$where_sql} GROUP BY p.id ORDER BY {$sort} {$direction},p.id DESC LIMIT %d OFFSET %d";
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...[...$values, $per_page, ($page - 1) * $per_page]), ARRAY_A) ?: [];

        return [
            'items' => array_map(static fn(array $row): array => [
                'id' => (int) $row['id'], 'supplierId' => (int) $row['supplier_id'], 'supplierName' => $row['supplier_name'],
                'externalId' => $row['external_id'], 'sku' => $row['sku'], 'ean' => $row['ean'], 'name' => $row['name'],
                'purchasePrice' => $row['purchase_price'] === null ? null : (float) $row['purchase_price'], 'currency' => $row['currency'],
                'taxRate' => $row['tax_rate'] === null ? null : (float) $row['tax_rate'], 'stockQuantity' => $row['stock_quantity'] === null ? null : (float) $row['stock_quantity'],
                'availability' => $row['availability'], 'supplierCategoryPath' => $row['supplier_category_path'], 'primaryImageUrl' => $row['primary_image_url'],
                'selected' => (bool) $row['selected'], 'wcProductId' => $row['wc_product_id'] ? (int) $row['wc_product_id'] : null,
                'version' => (int) $row['version'], 'updatedAt' => $row['updated_at'],
            ], $rows),
            'page' => $page, 'perPage' => $per_page, 'total' => $total, 'totalPages' => max(1, (int) ceil($total / $per_page)),
        ];
    }
}
