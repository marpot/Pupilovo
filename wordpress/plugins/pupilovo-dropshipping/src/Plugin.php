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
use Pupilovo\SupplierHub\Application\OrderFulfillmentSplitter;
use Pupilovo\SupplierHub\Infrastructure\Repository\AuditLogRepository;
use Pupilovo\SupplierHub\REST\FulfillmentRestController;

defined('ABSPATH') || exit;

final class Plugin {
    private const FULFILLMENT_CAPTURE_HOOK = 'pupilovo_sh_capture_fulfillment_order';
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
        add_action('rest_api_init', [FulfillmentRestController::class, 'register_routes']);
        add_action(JobQueue::IMPORT_HOOK, [self::class, 'process_import_batch'], 10, 2);
        add_action(SyncScheduler::HOOK, [self::class, 'process_scheduled_sync'], 10, 3);
        add_action(CatalogImportManager::HOOK, [self::class, 'process_catalog_ingest'], 10, 1);
        add_action('woocommerce_checkout_order_created', [self::class, 'capture_fulfillment_order'], 20, 1);
        add_action('woocommerce_store_api_checkout_order_processed', [self::class, 'capture_fulfillment_order'], 20, 1);
        add_action('woocommerce_new_order', [self::class, 'queue_fulfillment_capture'], 20, 1);
        add_action(self::FULFILLMENT_CAPTURE_HOOK, [self::class, 'capture_fulfillment_order'], 10, 1);
    }

    public static function process_import_batch(int $job_id, int $user_id): void {
        (new JobQueue())->process_import_batch($job_id, $user_id);
    }

    public static function process_scheduled_sync(int $supplier_id,int $user_id,array $managed_fields):void{(new SyncScheduler())->run($supplier_id,$user_id,$managed_fields);}
    public static function process_catalog_ingest(int$job_id):void{(new CatalogImportManager())->process($job_id);}
    public static function capture_fulfillment_order($order):void{try{(new OrderFulfillmentSplitter())->split($order);}catch (\Throwable $error){$order_id=$order instanceof \WC_Order?(int)$order->get_id():(int)$order;(new AuditLogRepository())->add(null,null,'error','fulfillment_split_failed','Nie udało się przygotować podziału zamówienia.',['orderId'=>$order_id,'errorType'=>get_class($error)]);}}
    public static function queue_fulfillment_capture(int$order_id):void{if($order_id>0&&function_exists('as_schedule_single_action'))as_schedule_single_action(time()+5,self::FULFILLMENT_CAPTURE_HOOK,['order'=>$order_id],JobQueue::GROUP,true);}
}
