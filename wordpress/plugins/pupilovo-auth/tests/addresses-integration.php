<?php
/** Run in the local WordPress container. Creates and deletes only its own fixtures. */
if (PHP_SAPI === 'cli-server' && getenv('PUPILOVO_ADDRESSES_TEST') === '1') {
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
$run = 'pupilovo-addresses-test-' . bin2hex(random_bytes(8));
$password = bin2hex(random_bytes(20));
$customers = [];
$server = null;
$jar = tempnam(sys_get_temp_dir(), 'pupilovo-addresses-cookies-');
$log = tempnam(sys_get_temp_dir(), 'pupilovo-addresses-log-');
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
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => max(60, (int) getenv('PUPILOVO_TEST_HTTP_TIMEOUT')),
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
    foreach (['owner', 'other'] as $name) {
        $id = wc_create_new_customer("$run-$name@example.com", '', $password);
        if (is_wp_error($id)) throw new RuntimeException('Fixture customer creation failed');
        $customers[$name] = $id;
        fwrite(STDOUT, "Prepared customer fixture: $name\n");
    }
    foreach ($customers as $name => $id) {
        $customer = new WC_Customer($id);
        $customer->set_billing_first_name($name);
        $customer->set_shipping_first_name($name);
        $customer->save();
    }
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $env = getenv(); $env['PUPILOVO_ADDRESSES_TEST'] = '1';
    $server = proc_open([PHP_BINARY, '-S', $address, __FILE__], [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, null, $env);
    for ($i = 0; $i < 50; $i++) {
        $ready = @stream_socket_client('tcp://' . $address, $errno, $error, .1);
        if ($ready) { fclose($ready); break; }
        usleep(100000);
    }
    [$status] = request('account/addresses');
    check($status === 401, 'anonymous address read denied');
    [$status] = request('account/addresses/billing', ['city' => 'Kraków']);
    check($status === 401, 'anonymous address write denied');
    request('auth/login', ['email' => "$run-owner@example.com", 'password' => $password]);
    [, $me] = request('auth/me');
    $nonce = $me['nonce'];
    foreach (['account/addresses', 'account/addresses/billing', 'account/addresses/shipping'] as $route) {
        $body = $route === 'account/addresses' ? null : ['city' => 'Kraków'];
        [$status] = request($route, $body);
        check($status === 401, 'cookie without nonce denied: ' . $route);
        [$status] = request($route, $body, 'invalid');
        check($status === 403, 'invalid nonce denied: ' . $route);
    }
    [$status, $data] = request('account/addresses?customer_id=' . $customers['other'], null, $nonce);
    check($status === 200 && $data['billing']['first_name'] === 'owner' && $data['shipping']['first_name'] === 'owner', 'only own addresses returned despite foreign customer_id');
    $billing = ['first_name' => '<b>Anna</b>', 'last_name' => 'Test', 'company' => '<i>Firma</i>',
        'address_1' => 'Testowa 1', 'address_2' => 'Lokal 2', 'city' => 'Kraków', 'postcode' => '30-001',
        'country' => 'pl', 'state' => '', 'phone' => '+48 500 500 500', 'email' => "$run-billing@example.com"];
    [$status, $data] = request('account/addresses/billing', $billing, $nonce);
    check($status === 200 && $data['billing']['first_name'] === 'Anna' && $data['billing']['company'] === 'Firma' && $data['billing']['country'] === 'PL', 'billing saved and sanitized');
    check($data['shipping']['first_name'] === 'owner', 'billing save preserves shipping');
    $savedBilling = $data['billing'];
    $shipping = $billing;
    unset($shipping['email']);
    $shipping['city'] = 'Warszawa'; $shipping['postcode'] = '00-001';
    [$status, $data] = request('account/addresses/shipping', $shipping, $nonce);
    check($status === 200 && $data['shipping']['city'] === 'Warszawa' && $data['shipping']['phone'] === $shipping['phone'], 'shipping saved including native phone');
    check($data['billing'] === $savedBilling, 'shipping save preserves billing');
    [, $reloaded] = request('account/addresses', null, $nonce);
    check($reloaded === $data, 'both addresses persisted across requests');
    check($me['user']['email'] !== $savedBilling['email'], 'billing contact does not change login email');
    foreach (['billing', 'shipping'] as $type) {
        [$status] = request('account/addresses/' . $type, ['customer_id' => $customers['other'], 'city' => 'Attack'], $nonce);
        check($status === 400, 'body customer_id rejected: ' . $type);
        [$status] = request('account/addresses/' . $type . '?customer_id=' . $customers['other'], ['city' => 'Attack'], $nonce);
        check($status === 400, 'query customer_id rejected: ' . $type);
    }
    foreach ([['email' => 'bad email'], ['postcode' => 'invalid'], ['country' => 'XX'], ['country' => 'US', 'state' => 'invalid'],
        ['phone' => 'abc'], ['first_name' => []], ['city' => null], ['city' => ''], ['city' => str_repeat('x', 501)], ['roles' => ['administrator']]] as $invalid) {
        [$status] = request('account/addresses/billing', $invalid, $nonce);
        check($status === 400, 'invalid input rejected: ' . json_encode(array_keys($invalid)));
    }
    [, $unchanged] = request('account/addresses', null, $nonce);
    check($unchanged === $reloaded, 'invalid requests do not partially save data');
    request('auth/logout', [], $nonce);
    [$status] = request('account/addresses', null, $nonce);
    check($status >= 400, 'logged out session cannot read addresses');
    request('auth/login', ['email' => "$run-other@example.com", 'password' => $password]);
    [, $me] = request('auth/me');
    [, $other] = request('account/addresses', null, $me['nonce']);
    check($other['billing']['first_name'] === 'other' && $other['shipping']['first_name'] === 'other' && $other['billing']['city'] === '', 'other customer unchanged and isolated after account switch');
    WP_Session_Tokens::get_instance($customers['other'])->destroy_all();
    [$status] = request('account/addresses/billing', ['city' => 'Attack'], $me['nonce']);
    check($status >= 400, 'expired session cannot save address');
    echo "$checks address integration checks passed\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    foreach ($customers as $id) {
        $user = get_user_by('id', $id);
        if ($user && str_starts_with($user->user_email, $run . '-')) wp_delete_user($id);
    }
    global $wpdb;
    foreach ($customers as $id) {
        $wpdb->delete($wpdb->prefix . 'woocommerce_sessions', ['session_key' => (string) $id], ['%s']);
    }
    unlink($jar); unlink($log);
    echo "Test customers and server cleaned up.\n";
}
