<?php
/**
 * Plugin Name: Pupilovo Dropshipping Adapter
 * Description: Minimal future supplier adapter contract. No supplier is connected.
 */
if (!defined('ABSPATH')) exit;

interface Pupilovo_Dropshipping_Adapter {
    public function fetch_products(): iterable;
    public function map_product(array $source): array;
}

final class Pupilovo_Dropshipping_Product_Map {
    public static function keys(): array { return ['sku', 'name', 'price', 'stock', 'description', 'images', 'status']; }
}
