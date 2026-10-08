<?php
/**
 * Plugin Name: Pupilovo Supplier Hub
 * Description: Universal supplier catalog, selection, import and synchronization foundation for WooCommerce.
 * Version: 0.3.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * Text Domain: pupilovo-supplier-hub
 */

defined('ABSPATH') || exit;

define('PUPILOVO_SUPPLIER_HUB_VERSION', '0.3.0');
define('PUPILOVO_SUPPLIER_HUB_SCHEMA_VERSION', '3');
define('PUPILOVO_SUPPLIER_HUB_FILE', __FILE__);
define('PUPILOVO_SUPPLIER_HUB_DIR', plugin_dir_path(__FILE__));
define('PUPILOVO_SUPPLIER_HUB_URL', plugin_dir_url(__FILE__));

/**
 * Legacy extension contract retained for existing supplier adapters.
 * New adapters should implement SupplierHub\Domain\Contract\APIAdapterInterface.
 */
interface Pupilovo_Dropshipping_Adapter {
    public function fetch_products(): iterable;

    public function map_product(array $supplier_product): array;
}

final class Pupilovo_Dropshipping_Product_Map {
    public static function keys(): array {
        return [
            'external_id',
            'sku',
            'ean',
            'name',
            'description',
            'short_description',
            'purchase_price',
            'tax_rate',
            'stock',
            'availability',
            'categories',
            'brand',
            'images',
            'attributes',
            'variants',
            'weight',
            'dimensions',
        ];
    }
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'Pupilovo\\SupplierHub\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = PUPILOVO_SUPPLIER_HUB_DIR . 'src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_readable($path)) {
        require_once $path;
    }
});

require_once PUPILOVO_SUPPLIER_HUB_DIR . 'includes/class-pupilovo-supplier-import.php';

register_activation_hook(
    __FILE__,
    [Pupilovo\SupplierHub\Infrastructure\Database\Schema::class, 'activate']
);

Pupilovo\SupplierHub\Plugin::boot();
