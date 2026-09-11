<?php
/** Run in the local WordPress container. Creates and deletes only its own fixtures. */
if (PHP_SAPI === 'cli-server' && getenv('PUPILOVO_ORDERS_TEST') === '1') {
    define('DISABLE_WP_CRON', true);
    require '/var/www/html/wp-load.php';
    add_filter('pre_wp_mail', '__return_true');
    rest_get_server()->serve_request(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    exit;
}
if (PHP_SAPI !== 'cli') { exit; }
define('DISABLE_WP_CRON', true);
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
add_filter('pre_wp_mail', '__return_true');
$run = 'pupilovo-orders-test-' . bin2hex(random_bytes(8));
$password = bin2hex(random_bytes(20));
$customers = [];
$orderIds = [];
$server = null;
$jar = tempnam(sys_get_temp_dir(), 'pupilovo-orders-cookies-');
$log = tempnam(sys_get_temp_dir(), 'pupilovo-orders-log-');
$checks = 0;
function check($condition, $label) {
    if (!$condition) { throw new RuntimeException("FAIL: $label"); }
    $GLOBALS['checks']++;
    echo "PASS: $label\n";
}
function request($endpoint, $body = null, $nonceHeader = null) {
    $c = curl_init('http://' . $GLOBALS['address'] . '/pupilovo/v1/' . $endpoint);
    $headers = ['Content-Type: application/json'];
    if ($nonceHeader !== null) $headers[] = 'X-WP-Nonce: ' . $nonceHeader;
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_COOKIEFILE => $GLOBALS['jar'], CURLOPT_COOKIEJAR => $GLOBALS['jar'],
        CURLOPT_HTTPHEADER => $headers]);
    if ($body !== null) { curl_setopt($c, CURLOPT_POSTFIELDS, json_encode($body)); }
    $start = microtime(true);
    $raw = curl_exec($c);
    $status = curl_getinfo($c, CURLINFO_RESPONSE_CODE);
    $error = curl_error($c);
    curl_close($c);
    if ($raw === false) throw new RuntimeException($error);
    $data = json_decode($raw, true);
    if (!is_array($data)) throw new RuntimeException('Non-JSON response: ' . substr($raw, 0, 500));
    printf("  %s: %d (%.2fs)\n", $endpoint, $status, microtime(true) - $start);
    return [$status, $data];
}

try {
    foreach (['owner', 'other', 'empty'] as $name) {
        $id = wc_create_new_customer("$run-$name@example.com", '', $password);
        if (is_wp_error($id)) throw new RuntimeException('Fixture customer creation failed');
        $customers[$name] = $id;
        fwrite(STDOUT, "Prepared customer fixture: $name\n");
    }
    foreach (array_merge(array_fill(0, 11, 'owner'), ['other', 'guest', 'draft']) as $i => $name) {
        $order = wc_create_order(['customer_id' => $name === 'guest' ? 0 : $customers[$name === 'draft' ? 'owner' : $name]]);
        if (is_wp_error($order)) throw new RuntimeException('Fixture order creation failed');
        $orderIds[] = $order->get_id();
        $order->set_billing_email("$run-owner@example.com");
        $order->set_currency('PLN');
        $order->set_status($name === 'draft' ? 'checkout-draft' : 'pending');
        $item = new WC_Order_Item_Product();
        $item->set_name('Produkt testowy <b>tekst</b>');
        $item->set_quantity(2);
        $item->set_subtotal('39.98');
        $item->set_total('39.98');
        $order->add_item($item);
        $order->calculate_totals();
        $order->save();
        fwrite(STDOUT, 'Prepared order fixture ' . ($i + 1) . "/14\n");
    }
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $env = getenv(); $env['PUPILOVO_ORDERS_TEST'] = '1';
    $server = proc_open([PHP_BINARY, '-S', $address, __FILE__], [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, null, $env);
    for ($i = 0; $i < 50; $i++) {
        $ready = @stream_socket_client('tcp://' . $address, $errno, $error, .1);
        if ($ready) { fclose($ready); break; }
        usleep(100000);
    }
    [$status] = request('account/orders');
    check($status === 401, 'anonymous order history denied');
    [$status] = request('auth/login', ['email' => "$run-owner@example.com", 'password' => $password]);
    check($status === 200, 'owner login');
    [, $me] = request('auth/me');
    $nonce = $me['nonce'];
    [$status] = request('account/orders');
    check($status === 401, 'cookie without REST nonce denied');
    [$status] = request('account/orders', null, 'invalid');
    check($status === 403, 'invalid REST nonce denied');
    [$status, $first] = request('account/orders', null, $nonce);
    check($status === 200 && count($first['orders']) === 10 && $first['total'] === 11 && $first['totalPages'] === 2, 'pagination counts only owned real orders');
    $expected = array_reverse(array_slice($orderIds, 0, 11));
    check(array_column($first['orders'], 'id') === array_slice($expected, 0, 10), 'newest orders first, foreign/guest/draft excluded');
    $item = $first['orders'][0];
    check($item['currency'] === 'PLN' && (float)$item['total'] === 39.98 && $item['items'][0]['quantity'] === 2, 'amount, currency and product quantity');
    check(!array_key_exists('billing', $item) && !array_key_exists('order_key', $item), 'no billing data or order access keys exposed');
    [$status, $second] = request('account/orders?page=2', null, $nonce);
    check($status === 200 && array_column($second['orders'], 'id') === array_slice($expected, 10), 'second page has remaining order');
    [$status, $outside] = request('account/orders?page=3', null, $nonce);
    check($status === 200 && $outside['orders'] === [], 'out of range page is empty');
    foreach (['0', '-1', 'abc', '1.5', '100001'] as $page) {
        [$status] = request('account/orders?page=' . $page, null, $nonce);
        check($status === 400, 'invalid page rejected: ' . $page);
    }
    [$status, $tampered] = request('account/orders?customer_id=' . $customers['other'] . '&customer=' . $customers['other'], null, $nonce);
    check($status === 200 && array_column($tampered['orders'], 'id') === array_slice($expected, 0, 10), 'client cannot select another customer');
    request('auth/logout', [], $nonce);
    [$status] = request('account/orders', null, $nonce);
    check($status >= 400, 'logged out session cannot read orders');
    request('auth/login', ['email' => "$run-empty@example.com", 'password' => $password]);
    [, $me] = request('auth/me');
    [$status, $empty] = request('account/orders', null, $me['nonce']);
    check($status === 200 && $empty['orders'] === [] && $empty['total'] === 0, 'customer with no orders receives empty history');
    echo "$checks order integration checks passed\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    foreach ($orderIds as $id) {
        $order = wc_get_order($id);
        if ($order) $order->delete(true);
    }
    foreach ($customers as $id) {
        $user = get_user_by('id', $id);
        if ($user && str_starts_with($user->user_email, $run . '-')) wp_delete_user($id);
    }
    unlink($jar); unlink($log);
    echo "Test orders, customers and server cleaned up.\n";
}
