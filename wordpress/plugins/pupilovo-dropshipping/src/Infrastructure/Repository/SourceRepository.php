<?php

namespace Pupilovo\SupplierHub\Infrastructure\Repository;

use Pupilovo\SupplierHub\Infrastructure\Database\Schema;

defined('ABSPATH') || exit;

final class SourceRepository {
    public function find_primary(int $supplier_id): ?array {
        global $wpdb;

        $table = Schema::table('supplier_sources');
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE supplier_id=%d AND active=1 ORDER BY id ASC LIMIT 1", $supplier_id),
            ARRAY_A
        );

        return is_array($row) ? $this->public_record($row) : null;
    }

    public function save_primary(int $supplier_id, array $input): array {
        global $wpdb;

        $table = Schema::table('supplier_sources');
        $existing = $this->find_primary($supplier_id);
        $now = current_time('mysql', true);
        $config = is_array($input['config'] ?? null) ? $input['config'] : [];
        $hash = hash('sha256', wp_json_encode([
            'type' => $input['sourceType'], 'location' => $input['location'] ?? null,
            'config' => $config, 'allowHttp' => !empty($input['allowInsecureHttp']),
            'complete' => !empty($input['declaresCompleteFeed']),
        ]));
        $data = [
            'name' => (string) ($input['name'] ?? 'Źródło główne'),
            'source_type' => $input['sourceType'],
            'location' => $input['location'] ?: null,
            'config' => wp_json_encode($config),
            'active' => 1,
            'allow_insecure_http' => !empty($input['allowInsecureHttp']) ? 1 : 0,
            'declares_complete_feed' => !empty($input['declaresCompleteFeed']) ? 1 : 0,
            'configuration_hash' => $hash,
            'updated_at' => $now,
        ];
        if ($existing) {
            $result = $wpdb->update($table, $data, ['id' => $existing['id']], null, ['%d']);
            $id = (int) $existing['id'];
        } else {
            $result = $wpdb->insert($table, [
                'uuid' => wp_generate_uuid4(), 'supplier_id' => $supplier_id,
                'created_at' => $now, ...$data,
            ], null);
            $id = (int) $wpdb->insert_id;
        }
        if ($result === false) {
            throw new \RuntimeException('Nie udało się zapisać źródła hurtowni.');
        }

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $id), ARRAY_A);

        return $this->public_record($row);
    }

    public function attach_secret(int $source_id, ?int $secret_id): void {
        global $wpdb;
        $wpdb->update(
            Schema::table('supplier_sources'),
            ['secret_id' => $secret_id, 'updated_at' => current_time('mysql', true)],
            ['id' => $source_id],
            ['%d', '%s'],
            ['%d']
        );
    }

    private function public_record(array $row): array {
        $config = json_decode((string) $row['config'], true);

        return [
            'id' => (int) $row['id'], 'uuid' => $row['uuid'], 'supplierId' => (int) $row['supplier_id'],
            'name' => $row['name'], 'sourceType' => $row['source_type'], 'location' => $row['location'] ?: null,
            'config' => is_array($config) ? $config : [], 'active' => (bool) $row['active'],
            'allowInsecureHttp' => (bool) $row['allow_insecure_http'], 'declaresCompleteFeed' => (bool) $row['declares_complete_feed'],
            'configurationHash' => $row['configuration_hash'], 'credentialsConfigured' => !empty($row['secret_id']),
            'createdAt' => $row['created_at'], 'updatedAt' => $row['updated_at'],
        ];
    }
}
