<?php
/** Run with WP-CLI eval-file against the local development installation only. */
if (!defined('WP_CLI') || !WP_CLI) {
    exit("Run this script through WP-CLI.\n");
}
if (!in_array(wp_parse_url(home_url(), PHP_URL_HOST), ['localhost', '127.0.0.1'], true)) {
    WP_CLI::error('This script is limited to the local development store.');
}
if (!class_exists('WooCommerce')) {
    WP_CLI::error('Activate WooCommerce first.');
}

if (!get_option('pupilovo_local_store_seeded')) {
    update_option('woocommerce_default_country', 'PL');
    update_option('woocommerce_currency', 'PLN');
    update_option('woocommerce_price_num_decimals', 2);
    update_option('woocommerce_price_decimal_sep', ',');
    update_option('woocommerce_price_thousand_sep', ' ');
    update_option('woocommerce_currency_pos', 'right_space');
    update_option('woocommerce_weight_unit', 'kg');
    update_option('woocommerce_dimension_unit', 'cm');
    update_option('woocommerce_allow_tracking', 'no');
}

$categories = [];
foreach (['psy' => 'Psy', 'koty' => 'Koty', 'gryzonie' => 'Gryzonie', 'ptaki' => 'Ptaki'] as $slug => $name) {
    $term = get_term_by('slug', $slug, 'product_cat');
    if ($term) {
        $categories[$slug] = $term->term_id;
        continue;
    }
    $result = wp_insert_term($name, 'product_cat', ['slug' => $slug]);
    if (is_wp_error($result)) {
        WP_CLI::error($result->get_error_message());
    }
    $categories[$slug] = $result['term_id'];
}

$products = [
    ['PUP-DEMO-001', 'Miska spowalniająca Pupilovo', '59.90', 'psy', 'Pomaga spowolnić jedzenie i wspiera zdrowe nawyki Twojego pupila.'],
    ['PUP-DEMO-002', 'Mata węchowa Pupilovo', '79.90', 'psy', 'Zabawa, która angażuje naturalny węch i zapewnia psu dodatkową aktywność.'],
    ['PUP-DEMO-003', 'Zabawka interaktywna Pupilovo', '49.90', 'koty', 'Pomaga zapewnić pupilowi zajęcie i rozwijać jego naturalną ciekawość.'],
    ['PUP-DEMO-004', 'Szczotka pielęgnacyjna Pupilovo', '39.90', 'koty', 'Delikatna pielęgnacja sierści i przyjemny masaż podczas codziennego czesania.'],
];

foreach ($products as [$sku, $name, $price, $category, $description]) {
    if (wc_get_product_id_by_sku($sku)) {
        WP_CLI::log("Already exists: $sku (unchanged)");
        continue;
    }
    $product = new WC_Product_Simple();
    $product->set_name($name);
    $product->set_sku($sku);
    $product->set_status('publish');
    $product->set_catalog_visibility('visible');
    $product->set_regular_price($price);
    $product->set_short_description($description);
    $product->set_description($description . "\n\nProdukt demonstracyjny do testów lokalnych — nie jest ofertą handlową.");
    $product->set_category_ids([$categories[$category]]);
    $product->set_manage_stock(true);
    $product->set_stock_quantity(20);
    $product->update_meta_data('_pupilovo_demo', 'yes');
    $id = $product->save();
    if (!$id) {
        WP_CLI::error("Could not create $sku");
    }
    WP_CLI::log("Created $sku (ID: $id)");
}

WC_Install::create_pages();
update_option('pupilovo_local_store_seeded', 1);
WP_CLI::success('Local catalog ready. Existing products were not overwritten.');
