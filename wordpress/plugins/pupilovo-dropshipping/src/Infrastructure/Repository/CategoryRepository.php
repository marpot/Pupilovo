<?php

namespace Pupilovo\SupplierHub\Infrastructure\Repository;

use Pupilovo\SupplierHub\Infrastructure\Database\Schema;

defined('ABSPATH') || exit;

final class CategoryRepository {
    public function upsert_paths(int $supplier_id, array $paths): void {
        global $wpdb;
        $table = Schema::table('supplier_categories');
        foreach ($paths as $raw_path) {
            if (!is_scalar($raw_path) || trim((string) $raw_path) === '') { continue; }
            $parts = preg_split('/\s*(?:>|\/|→)\s*/u', trim((string) $raw_path)) ?: [];
            $built = []; $parent_external = null;
            foreach ($parts as $name) {
                $name = sanitize_text_field($name);
                if ($name === '') { continue; }
                $built[] = $name; $path = implode(' > ', $built); $external = hash('sha256', $path);
                $sql = "INSERT INTO {$table} (supplier_id,external_id,parent_external_id,name,path,normalized_name,updated_at)
                        VALUES (%d,%s,%s,%s,%s,%s,%s)
                        ON DUPLICATE KEY UPDATE parent_external_id=VALUES(parent_external_id),name=VALUES(name),path=VALUES(path),normalized_name=VALUES(normalized_name),updated_at=VALUES(updated_at)";
                $wpdb->query($wpdb->prepare($sql, $supplier_id, $external, $parent_external, $name, $path, sanitize_title($name), current_time('mysql', true)));
                $parent_external = $external;
            }
        }
    }

    public function list(int $supplier_id): array {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT c.*,m.wc_term_id,m.decision,m.match_method,m.confidence FROM ' . Schema::table('supplier_categories') . ' c LEFT JOIN ' . Schema::table('category_mappings') . ' m ON m.supplier_category_id=c.id WHERE c.supplier_id=%d ORDER BY c.path',
            $supplier_id
        ), ARRAY_A) ?: [];

        return array_map(static fn(array $row): array => [
            'id' => (int) $row['id'], 'supplierId' => (int) $row['supplier_id'], 'externalId' => $row['external_id'],
            'name' => $row['name'], 'path' => $row['path'], 'wcTermId' => $row['wc_term_id'] ? (int) $row['wc_term_id'] : null,
            'decision' => $row['decision'] ?: 'pending', 'matchMethod' => $row['match_method'],
            'confidence' => $row['confidence'] === null ? null : (float) $row['confidence'],
        ], $rows);
    }

    public function find(int $id): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Schema::table('supplier_categories') . ' WHERE id=%d', $id), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public function save_mapping(int $category_id, ?int $term_id, string $decision, string $method, ?float $confidence, int $user_id): void {
        global $wpdb;
        $category = $this->find($category_id);
        if (!$category) { throw new \InvalidArgumentException('Nie znaleziono kategorii dostawcy.'); }
        $table = Schema::table('category_mappings'); $now = current_time('mysql', true);
        $sql = "INSERT INTO {$table} (supplier_id,supplier_category_id,wc_term_id,decision,match_method,confidence,approved_by,approved_at,created_at,updated_at)
                VALUES (%d,%d,%d,%s,%s,%f,%d,%s,%s,%s)
                ON DUPLICATE KEY UPDATE wc_term_id=VALUES(wc_term_id),decision=VALUES(decision),match_method=VALUES(match_method),confidence=VALUES(confidence),approved_by=VALUES(approved_by),approved_at=VALUES(approved_at),updated_at=VALUES(updated_at)";
        $wpdb->query($wpdb->prepare($sql, $category['supplier_id'], $category_id, $term_id, $decision, $method, $confidence, $user_id, $now, $now, $now));
    }
}
