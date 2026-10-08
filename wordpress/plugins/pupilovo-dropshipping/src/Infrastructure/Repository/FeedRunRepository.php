<?php

namespace Pupilovo\SupplierHub\Infrastructure\Repository;

use Pupilovo\SupplierHub\Infrastructure\Database\Schema;

defined('ABSPATH') || exit;

final class FeedRunRepository {
    public function begin(int $supplier_id, ?int $source_id, string $configuration_hash, array $context = []): int {
        global $wpdb;

        $table = Schema::table('feed_runs');
        $inserted = $wpdb->insert($table, [
            'uuid' => wp_generate_uuid4(),
            'supplier_id' => $supplier_id,
            'source_id' => $source_id,
            'status' => 'running',
            'completeness_status' => 'unknown',
            'configuration_hash' => $configuration_hash,
            'context' => wp_json_encode($context),
            'started_at' => current_time('mysql', true),
        ], ['%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s']);
        if ($inserted !== 1) {
            throw new \RuntimeException('Nie udało się rozpocząć przebiegu feedu.');
        }

        return (int) $wpdb->insert_id;
    }

    public function previous_complete_count(int $supplier_id, int $exclude_id): ?int {
        global $wpdb;

        $value = $wpdb->get_var($wpdb->prepare(
            'SELECT records_valid FROM ' . Schema::table('feed_runs') . "
             WHERE supplier_id = %d AND id <> %d AND status = 'completed' AND completeness_status = 'complete'
             ORDER BY id DESC LIMIT 1",
            $supplier_id,
            $exclude_id
        ));

        return $value === null ? null : (int) $value;
    }

    public function complete(int $id, array $metrics): void {
        global $wpdb;

        $result = $wpdb->update(
            Schema::table('feed_runs'),
            [
                'status' => 'completed',
                'completeness_status' => $metrics['completeness'],
                'source_checksum' => $metrics['sourceChecksum'] ?: null,
                'records_seen' => $metrics['seen'],
                'records_valid' => $metrics['valid'],
                'records_invalid' => $metrics['invalid'],
                'records_upserted' => $metrics['upserted'],
                'previous_complete_count' => $metrics['previousCount'],
                'context' => wp_json_encode($metrics['context']),
                'finished_at' => current_time('mysql', true),
            ],
            ['id' => $id],
            ['%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s'],
            ['%d']
        );
        if ($result === false) {
            throw new \RuntimeException('Nie udało się zakończyć przebiegu feedu.');
        }
    }

    public function fail(int $id, string $message, array $metrics = []): void {
        global $wpdb;
        $wpdb->update(
            Schema::table('feed_runs'),
            [
                'status' => 'failed',
                'completeness_status' => 'invalid',
                'records_seen' => (int) ($metrics['seen'] ?? 0),
                'records_valid' => (int) ($metrics['valid'] ?? 0),
                'records_invalid' => (int) ($metrics['invalid'] ?? 0),
                'error_summary' => mb_substr(wp_strip_all_tags($message), 0, 2000),
                'finished_at' => current_time('mysql', true),
            ],
            ['id' => $id],
            ['%s', '%s', '%d', '%d', '%d', '%s', '%s'],
            ['%d']
        );
    }
}
