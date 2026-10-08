<?php

namespace Pupilovo\SupplierHub\Infrastructure\Repository;

use Pupilovo\SupplierHub\Infrastructure\Database\Schema;

defined('ABSPATH') || exit;

final class SupplierRepository {
    public function paginate(int $page, int $per_page, string $search = '', string $status = ''): array {
        global $wpdb;

        $table = Schema::table('suppliers');
        $where = ["status <> 'deleted'"];
        $values = [];

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(name LIKE %s OR slug LIKE %s)';
            $values[] = $like;
            $values[] = $like;
        }
        if ($status !== '') {
            $where[] = 'status = %s';
            $values[] = $status;
        }

        $where_sql = implode(' AND ', $where);
        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
        $list_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY updated_at DESC, id DESC LIMIT %d OFFSET %d";
        $offset = ($page - 1) * $per_page;

        $total = (int) $wpdb->get_var($values ? $wpdb->prepare($count_sql, ...$values) : $count_sql);
        $list_values = [...$values, $per_page, $offset];
        $rows = $wpdb->get_results($wpdb->prepare($list_sql, ...$list_values), ARRAY_A) ?: [];

        return [
            'items' => array_map([$this, 'public_record'], $rows),
            'page' => $page,
            'perPage' => $per_page,
            'total' => $total,
            'totalPages' => max(1, (int) ceil($total / $per_page)),
        ];
    }

    public function find(int $id): ?array {
        global $wpdb;

        $table = Schema::table('suppliers');
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d AND status <> 'deleted'", $id),
            ARRAY_A
        );

        return is_array($row) ? $this->public_record($row) : null;
    }

    public function create(array $input, int $user_id): array {
        global $wpdb;

        $table = Schema::table('suppliers');
        $now = current_time('mysql', true);
        $slug = $this->unique_slug((string) $input['name']);
        $inserted = $wpdb->insert(
            $table,
            [
                'uuid' => wp_generate_uuid4(),
                'name' => $input['name'],
                'slug' => $slug,
                'source_type' => $input['sourceType'],
                'adapter_key' => $input['adapterKey'] ?: null,
                'status' => $input['status'],
                'sync_enabled' => 0,
                'source_config' => wp_json_encode($input['sourceConfig']),
                'field_mapping' => wp_json_encode($input['fieldMapping']),
                'schedule_config' => wp_json_encode([]),
                'created_by' => $user_id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        if ($inserted !== 1) {
            throw new \RuntimeException('Nie udało się zapisać hurtowni.');
        }

        return $this->find((int) $wpdb->insert_id) ?? [];
    }

    public function update(int $id, array $input): ?array {
        global $wpdb;

        if ($this->find($id) === null) {
            return null;
        }

        $table = Schema::table('suppliers');
        $data = ['updated_at' => current_time('mysql', true)];
        $formats = ['%s'];
        $map = [
            'name' => ['name', '%s'],
            'sourceType' => ['source_type', '%s'],
            'adapterKey' => ['adapter_key', '%s'],
            'status' => ['status', '%s'],
            'syncEnabled' => ['sync_enabled', '%d'],
        ];
        foreach ($map as $source => [$target, $format]) {
            if (array_key_exists($source, $input)) {
                $data[$target] = $input[$source];
                $formats[] = $format;
            }
        }
        foreach (['sourceConfig' => 'source_config', 'fieldMapping' => 'field_mapping'] as $source => $target) {
            if (array_key_exists($source, $input)) {
                $data[$target] = wp_json_encode($input[$source]);
                $formats[] = '%s';
            }
        }

        $wpdb->update($table, $data, ['id' => $id], $formats, ['%d']);

        return $this->find($id);
    }

    public function soft_delete(int $id): bool {
        global $wpdb;

        $table = Schema::table('suppliers');
        $result = $wpdb->update(
            $table,
            ['status' => 'deleted', 'sync_enabled' => 0, 'updated_at' => current_time('mysql', true)],
            ['id' => $id],
            ['%s', '%d', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    private function unique_slug(string $name): string {
        global $wpdb;

        $table = Schema::table('suppliers');
        $base = sanitize_title($name) ?: 'hurtownia';
        $slug = $base;
        $suffix = 2;
        while ((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE slug = %s", $slug)) > 0) {
            $slug = $base . '-' . $suffix;
            ++$suffix;
        }

        return $slug;
    }

    private function public_record(array $row): array {
        return [
            'id' => (int) $row['id'],
            'uuid' => (string) $row['uuid'],
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
            'sourceType' => (string) $row['source_type'],
            'adapterKey' => $row['adapter_key'] ?: null,
            'status' => (string) $row['status'],
            'syncEnabled' => (bool) $row['sync_enabled'],
            'sourceConfig' => $this->decode_json($row['source_config']),
            'fieldMapping' => $this->decode_json($row['field_mapping']),
            'scheduleConfig' => $this->decode_json($row['schedule_config']),
            'lastImportAt' => $row['last_import_at'] ?: null,
            'lastSyncAt' => $row['last_sync_at'] ?: null,
            'createdAt' => (string) $row['created_at'],
            'updatedAt' => (string) $row['updated_at'],
        ];
    }

    private function decode_json(?string $value): array {
        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
