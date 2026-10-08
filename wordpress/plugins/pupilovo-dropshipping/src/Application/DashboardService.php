<?php

namespace Pupilovo\SupplierHub\Application;

use Pupilovo\SupplierHub\Infrastructure\Database\Schema;

defined('ABSPATH') || exit;

final class DashboardService {
    public function metrics(): array {
        global $wpdb;

        $suppliers = Schema::table('suppliers');
        $catalog = Schema::table('catalog_products');
        $selections = Schema::table('product_selections');
        $links = Schema::table('product_links');
        $logs = Schema::table('logs');

        return [
            'connectedSuppliers' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$suppliers} WHERE status = 'active'"
            ),
            'catalogProducts' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$catalog}"),
            'selectedProducts' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$selections}"),
            'importedProducts' => (int) $wpdb->get_var(
                "SELECT COUNT(DISTINCT wc_product_id) FROM {$links} WHERE relationship_status = 'linked'"
            ),
            'unavailableProducts' => (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM {$catalog} WHERE availability = %s", 'unavailable')
            ),
            'priceChangesPending' => 0,
            'attentionErrors' => (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$logs} WHERE severity IN (%s,%s) AND created_at >= %s",
                    'error',
                    'critical',
                    gmdate('Y-m-d H:i:s', time() - WEEK_IN_SECONDS)
                )
            ),
            'lastSyncAt' => $wpdb->get_var(
                "SELECT MAX(last_sync_at) FROM {$suppliers} WHERE status <> 'deleted'"
            ) ?: null,
        ];
    }
}
