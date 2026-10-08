<?php

namespace Pupilovo\SupplierHub\Infrastructure\Database;

defined('ABSPATH') || exit;

final class Schema {
    public const OPTION = 'pupilovo_supplier_hub_schema_version';

    public static function activate(): void {
        self::install();
        self::grant_capabilities();
        self::migrate_legacy_profiles();
    }

    public static function maybe_upgrade(): void {
        if (get_option(self::OPTION) !== PUPILOVO_SUPPLIER_HUB_SCHEMA_VERSION) {
            self::activate();
        }
    }

    public static function table(string $name): string {
        global $wpdb;

        return $wpdb->prefix . 'pupilovo_sh_' . $name;
    }

    public static function install(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $suppliers = self::table('suppliers');
        $catalog = self::table('catalog_products');
        $categories = self::table('supplier_categories');
        $category_mappings = self::table('category_mappings');
        $selections = self::table('product_selections');
        $links = self::table('product_links');
        $pricing = self::table('pricing_rules');
        $jobs = self::table('jobs');
        $job_items = self::table('job_items');
        $logs = self::table('logs');
        $sources = self::table('supplier_sources');
        $secrets = self::table('secrets');
        $feed_runs = self::table('feed_runs');
        $canonical_products = self::table('canonical_products');
        $offers = self::table('supplier_offers');
        $history = self::table('product_history');

        $queries = [
            "CREATE TABLE {$suppliers} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                uuid char(36) NOT NULL,
                name varchar(191) NOT NULL,
                slug varchar(191) NOT NULL,
                source_type varchar(32) NOT NULL,
                adapter_key varchar(100) NULL,
                status varchar(24) NOT NULL DEFAULT 'draft',
                sync_enabled tinyint(1) NOT NULL DEFAULT 0,
                source_config longtext NULL,
                credentials_ciphertext longtext NULL,
                field_mapping longtext NULL,
                schedule_config longtext NULL,
                source_revision bigint(20) unsigned NOT NULL DEFAULT 1,
                last_import_at datetime NULL,
                last_sync_at datetime NULL,
                created_by bigint(20) unsigned NOT NULL DEFAULT 0,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                UNIQUE KEY slug (slug),
                KEY status (status),
                KEY source_type (source_type)
            ) {$charset};",
            "CREATE TABLE {$catalog} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                supplier_id bigint(20) unsigned NOT NULL,
                external_id varchar(191) NOT NULL,
                sku varchar(191) NULL,
                ean varchar(32) NULL,
                name text NOT NULL,
                description longtext NULL,
                short_description text NULL,
                purchase_price decimal(19,6) NULL,
                currency char(3) NULL,
                tax_rate decimal(7,4) NULL,
                stock_quantity decimal(19,4) NULL,
                availability varchar(40) NULL,
                supplier_category_path text NULL,
                primary_image_url text NULL,
                normalized_payload longtext NULL,
                raw_payload longtext NULL,
                checksum char(64) NULL,
                feed_run_id bigint(20) unsigned NULL,
                record_status varchar(24) NOT NULL DEFAULT 'valid',
                validation_errors longtext NULL,
                version bigint(20) unsigned NOT NULL DEFAULT 1,
                first_seen_at datetime NOT NULL,
                last_seen_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY supplier_external (supplier_id,external_id),
                KEY supplier_sku (supplier_id,sku),
                KEY supplier_ean (supplier_id,ean),
                KEY supplier_availability (supplier_id,availability),
                KEY supplier_record_status (supplier_id,record_status),
                KEY feed_run_id (feed_run_id),
                KEY last_seen_at (last_seen_at)
            ) {$charset};",
            "CREATE TABLE {$categories} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                supplier_id bigint(20) unsigned NOT NULL,
                external_id varchar(191) NOT NULL,
                parent_external_id varchar(191) NULL,
                name varchar(255) NOT NULL,
                path text NOT NULL,
                normalized_name varchar(255) NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY supplier_external (supplier_id,external_id),
                KEY supplier_parent (supplier_id,parent_external_id)
            ) {$charset};",
            "CREATE TABLE {$category_mappings} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                supplier_id bigint(20) unsigned NOT NULL,
                supplier_category_id bigint(20) unsigned NOT NULL,
                wc_term_id bigint(20) unsigned NULL,
                decision varchar(24) NOT NULL DEFAULT 'pending',
                match_method varchar(32) NULL,
                confidence decimal(5,4) NULL,
                approved_by bigint(20) unsigned NULL,
                approved_at datetime NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY supplier_category (supplier_id,supplier_category_id),
                KEY wc_term_id (wc_term_id),
                KEY decision (decision)
            ) {$charset};",
            "CREATE TABLE {$selections} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                supplier_id bigint(20) unsigned NOT NULL,
                catalog_product_id bigint(20) unsigned NOT NULL,
                selected_by bigint(20) unsigned NOT NULL,
                selected_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY supplier_product (supplier_id,catalog_product_id),
                KEY selected_at (selected_at)
            ) {$charset};",
            "CREATE TABLE {$links} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                supplier_id bigint(20) unsigned NOT NULL,
                catalog_product_id bigint(20) unsigned NOT NULL,
                external_id varchar(191) NOT NULL,
                wc_product_id bigint(20) unsigned NOT NULL,
                relationship_status varchar(24) NOT NULL DEFAULT 'linked',
                is_primary tinyint(1) NOT NULL DEFAULT 0,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY supplier_external (supplier_id,external_id),
                KEY wc_product_id (wc_product_id),
                KEY catalog_product_id (catalog_product_id)
            ) {$charset};",
            "CREATE TABLE {$pricing} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                supplier_id bigint(20) unsigned NOT NULL,
                scope_type varchar(24) NOT NULL DEFAULT 'supplier',
                scope_id bigint(20) unsigned NULL,
                priority int(11) NOT NULL DEFAULT 100,
                active tinyint(1) NOT NULL DEFAULT 1,
                rule_config longtext NOT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY supplier_scope (supplier_id,scope_type,scope_id),
                KEY active_priority (active,priority)
            ) {$charset};",
            "CREATE TABLE {$jobs} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                uuid char(36) NOT NULL,
                supplier_id bigint(20) unsigned NULL,
                job_type varchar(32) NOT NULL,
                status varchar(24) NOT NULL DEFAULT 'pending',
                dry_run tinyint(1) NOT NULL DEFAULT 1,
                total_items bigint(20) unsigned NOT NULL DEFAULT 0,
                processed_items bigint(20) unsigned NOT NULL DEFAULT 0,
                succeeded_items bigint(20) unsigned NOT NULL DEFAULT 0,
                failed_items bigint(20) unsigned NOT NULL DEFAULT 0,
                cursor_value text NULL,
                context longtext NULL,
                requested_by bigint(20) unsigned NOT NULL DEFAULT 0,
                created_at datetime NOT NULL,
                started_at datetime NULL,
                finished_at datetime NULL,
                heartbeat_at datetime NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                KEY supplier_status (supplier_id,status),
                KEY job_type_status (job_type,status),
                KEY created_at (created_at)
            ) {$charset};",
            "CREATE TABLE {$job_items} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                job_id bigint(20) unsigned NOT NULL,
                catalog_product_id bigint(20) unsigned NULL,
                action_type varchar(32) NOT NULL,
                status varchar(24) NOT NULL DEFAULT 'pending',
                wc_product_id bigint(20) unsigned NULL,
                attempts smallint(5) unsigned NOT NULL DEFAULT 0,
                result_summary text NULL,
                error_code varchar(100) NULL,
                before_snapshot longtext NULL,
                after_snapshot longtext NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY job_product_action (job_id,catalog_product_id,action_type),
                KEY job_status (job_id,status),
                KEY wc_product_id (wc_product_id)
            ) {$charset};",
            "CREATE TABLE {$logs} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                supplier_id bigint(20) unsigned NULL,
                job_id bigint(20) unsigned NULL,
                severity varchar(16) NOT NULL,
                event_code varchar(100) NOT NULL,
                message text NOT NULL,
                context longtext NULL,
                fingerprint char(64) NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY supplier_created (supplier_id,created_at),
                KEY job_created (job_id,created_at),
                KEY severity_created (severity,created_at),
                KEY fingerprint (fingerprint)
            ) {$charset};",
            "CREATE TABLE {$sources} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                uuid char(36) NOT NULL,
                supplier_id bigint(20) unsigned NOT NULL,
                name varchar(191) NOT NULL,
                source_type varchar(32) NOT NULL,
                location text NULL,
                config longtext NULL,
                secret_id bigint(20) unsigned NULL,
                active tinyint(1) NOT NULL DEFAULT 1,
                allow_insecure_http tinyint(1) NOT NULL DEFAULT 0,
                declares_complete_feed tinyint(1) NOT NULL DEFAULT 0,
                configuration_hash char(64) NOT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                KEY supplier_active (supplier_id,active),
                KEY secret_id (secret_id)
            ) {$charset};",
            "CREATE TABLE {$secrets} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                supplier_id bigint(20) unsigned NOT NULL,
                secret_name varchar(100) NOT NULL,
                ciphertext longtext NOT NULL,
                key_id char(16) NOT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY supplier_secret (supplier_id,secret_name),
                KEY key_id (key_id)
            ) {$charset};",
            "CREATE TABLE {$feed_runs} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                uuid char(36) NOT NULL,
                supplier_id bigint(20) unsigned NOT NULL,
                source_id bigint(20) unsigned NULL,
                status varchar(24) NOT NULL DEFAULT 'pending',
                completeness_status varchar(24) NOT NULL DEFAULT 'unknown',
                source_checksum char(64) NULL,
                configuration_hash char(64) NOT NULL,
                records_seen bigint(20) unsigned NOT NULL DEFAULT 0,
                records_valid bigint(20) unsigned NOT NULL DEFAULT 0,
                records_invalid bigint(20) unsigned NOT NULL DEFAULT 0,
                records_upserted bigint(20) unsigned NOT NULL DEFAULT 0,
                previous_complete_count bigint(20) unsigned NULL,
                context longtext NULL,
                error_summary text NULL,
                started_at datetime NOT NULL,
                finished_at datetime NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                KEY supplier_started (supplier_id,started_at),
                KEY source_status (source_id,status),
                KEY completeness_status (completeness_status)
            ) {$charset};",
            "CREATE TABLE {$canonical_products} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                uuid char(36) NOT NULL,
                gtin varchar(32) NULL,
                normalized_name text NULL,
                status varchar(24) NOT NULL DEFAULT 'candidate',
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                UNIQUE KEY gtin (gtin),
                KEY status (status)
            ) {$charset};",
            "CREATE TABLE {$offers} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                supplier_id bigint(20) unsigned NOT NULL,
                catalog_product_id bigint(20) unsigned NOT NULL,
                canonical_product_id bigint(20) unsigned NULL,
                external_id varchar(191) NOT NULL,
                supplier_sku varchar(191) NULL,
                purchase_price decimal(19,6) NULL,
                currency char(3) NULL,
                stock_quantity decimal(19,4) NULL,
                availability varchar(40) NULL,
                preferred tinyint(1) NOT NULL DEFAULT 0,
                offer_status varchar(24) NOT NULL DEFAULT 'active',
                last_feed_run_id bigint(20) unsigned NULL,
                first_seen_at datetime NOT NULL,
                last_seen_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY supplier_external (supplier_id,external_id),
                UNIQUE KEY catalog_product_id (catalog_product_id),
                KEY canonical_status (canonical_product_id,offer_status),
                KEY supplier_preferred (supplier_id,preferred),
                KEY last_feed_run_id (last_feed_run_id)
            ) {$charset};",
            "CREATE TABLE {$history} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                catalog_product_id bigint(20) unsigned NOT NULL,
                feed_run_id bigint(20) unsigned NOT NULL,
                change_type varchar(24) NOT NULL,
                changed_fields longtext NULL,
                previous_checksum char(64) NULL,
                current_checksum char(64) NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY product_created (catalog_product_id,created_at),
                KEY feed_run_id (feed_run_id),
                KEY change_type (change_type)
            ) {$charset};",
        ];

        foreach ($queries as $query) {
            dbDelta($query);
        }

        self::migrate_v1_sources();

        update_option(self::OPTION, PUPILOVO_SUPPLIER_HUB_SCHEMA_VERSION, false);
    }

    private static function migrate_v1_sources(): void {
        global $wpdb;

        $suppliers = self::table('suppliers');
        $sources = self::table('supplier_sources');
        $rows = $wpdb->get_results(
            "SELECT id, uuid, name, source_type, source_config, created_at, updated_at
             FROM {$suppliers}
             WHERE status <> 'deleted'",
            ARRAY_A
        ) ?: [];
        foreach ($rows as $row) {
            $exists = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM {$sources} WHERE supplier_id = %d", $row['id'])
            );
            if ($exists > 0) {
                continue;
            }
            $config = json_decode((string) $row['source_config'], true);
            $config = is_array($config) ? $config : [];
            $hash = hash('sha256', wp_json_encode([
                'type' => $row['source_type'],
                'config' => $config,
            ]));
            $wpdb->insert(
                $sources,
                [
                    'uuid' => wp_generate_uuid4(),
                    'supplier_id' => (int) $row['id'],
                    'name' => (string) $row['name'] . ' — źródło główne',
                    'source_type' => (string) $row['source_type'],
                    'location' => isset($config['url']) ? esc_url_raw((string) $config['url']) : null,
                    'config' => wp_json_encode($config),
                    'active' => 1,
                    'allow_insecure_http' => 0,
                    'declares_complete_feed' => 0,
                    'configuration_hash' => $hash,
                    'created_at' => (string) $row['created_at'],
                    'updated_at' => (string) $row['updated_at'],
                ],
                ['%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s']
            );
        }
    }

    private static function grant_capabilities(): void {
        foreach (['administrator', 'shop_manager'] as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                $role->add_cap('manage_pupilovo_supplier_hub');
            }
        }
    }

    private static function migrate_legacy_profiles(): void {
        global $wpdb;

        $profiles = get_option('pupilovo_supplier_profiles', []);
        if (!is_array($profiles) || $profiles === []) {
            return;
        }

        $table = self::table('suppliers');
        $now = current_time('mysql', true);
        foreach ($profiles as $profile) {
            if (!is_array($profile) || empty($profile['name'])) {
                continue;
            }

            $legacy_id = sanitize_key((string) ($profile['id'] ?? ''));
            $slug = sanitize_title((string) $profile['name']);
            if ($legacy_id !== '') {
                $slug .= '-' . substr(md5($legacy_id), 0, 8);
            }

            $format = ($profile['format'] ?? 'xml') === 'csv' ? 'csv' : 'xml';
            $wpdb->query(
                $wpdb->prepare(
                    "INSERT IGNORE INTO {$table}
                    (uuid,name,slug,source_type,status,source_config,field_mapping,created_by,created_at,updated_at)
                    VALUES (%s,%s,%s,%s,'draft',%s,%s,0,%s,%s)",
                    wp_generate_uuid4(),
                    sanitize_text_field((string) $profile['name']),
                    $slug,
                    'file_' . $format,
                    wp_json_encode([
                        'record_element' => sanitize_key((string) ($profile['record_element'] ?? 'product')),
                        'delimiter' => substr((string) ($profile['delimiter'] ?? ';'), 0, 1),
                        'migrated_from' => 'pupilovo_supplier_profiles',
                    ]),
                    wp_json_encode(is_array($profile['mapping'] ?? null) ? $profile['mapping'] : []),
                    $now,
                    $now
                )
            );
        }
    }
}
