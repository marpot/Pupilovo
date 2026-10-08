<?php

namespace Pupilovo\SupplierHub;

use Pupilovo\SupplierHub\Admin\AdminPage;
use Pupilovo\SupplierHub\Infrastructure\Database\Schema;
use Pupilovo\SupplierHub\REST\AdminRestController;
use Pupilovo\SupplierHub\REST\CatalogRestController;
use Pupilovo\SupplierHub\REST\SourceRestController;
use Pupilovo\SupplierHub\REST\CategorySelectionRestController;
use Pupilovo\SupplierHub\REST\ImportRestController;
use Pupilovo\SupplierHub\Application\JobQueue;
use Pupilovo\SupplierHub\Application\SyncScheduler;
use Pupilovo\SupplierHub\REST\SyncRestController;
use Pupilovo\SupplierHub\REST\DiagnosticsRestController;
use Pupilovo\SupplierHub\Application\CatalogImportManager;

defined('ABSPATH') || exit;

final class Plugin {
    private static bool $booted = false;

    public static function boot(): void {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        add_action('plugins_loaded', [Schema::class, 'maybe_upgrade'], 5);
        add_action('admin_menu', [AdminPage::class, 'register']);
        add_action('admin_enqueue_scripts', [AdminPage::class, 'enqueue_assets']);
        add_action('rest_api_init', [AdminRestController::class, 'register_routes']);
        add_action('rest_api_init', [CatalogRestController::class, 'register_routes']);
        add_action('rest_api_init', [SourceRestController::class, 'register_routes']);
        add_action('rest_api_init', [CategorySelectionRestController::class, 'register_routes']);
        add_action('rest_api_init', [ImportRestController::class, 'register_routes']);
        add_action('rest_api_init', [SyncRestController::class, 'register_routes']);
        add_action('rest_api_init', [DiagnosticsRestController::class, 'register_routes']);
        add_action(JobQueue::IMPORT_HOOK, [self::class, 'process_import_batch'], 10, 2);
        add_action(SyncScheduler::HOOK, [self::class, 'process_scheduled_sync'], 10, 3);
        add_action(CatalogImportManager::HOOK, [self::class, 'process_catalog_ingest'], 10, 1);
    }

    public static function process_import_batch(int $job_id, int $user_id): void {
        (new JobQueue())->process_import_batch($job_id, $user_id);
    }

    public static function process_scheduled_sync(int $supplier_id,int $user_id,array $managed_fields):void{(new SyncScheduler())->run($supplier_id,$user_id,$managed_fields);}
    public static function process_catalog_ingest(int$job_id):void{(new CatalogImportManager())->process($job_id);}
}
