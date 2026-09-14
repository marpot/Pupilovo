<?php

/**
 * Integration test for issue #16:
 * customer identity + WooCommerce Cart-Token + Store API checkout.
 *
 * Run inside the local WordPress container.
 * Creates and removes only its own fixtures.
 */

if (PHP_SAPI === 'cli-server' && getenv('PUPILOVO_CART_SESSION_TEST') === '1') {
    define('DISABLE_WP_CRON', true);

    require '/var/www/html/wp-load.php';

    add_filter('pre_wp_mail', '__return_true');
    add_filter('pre_transient_pupilovo_google_certs', fn() => ['integration' => getenv('PUPILOVO_CART_GOOGLE_KEY')]);

    $route = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $GLOBALS['wp']->query_vars['rest_route'] = $route;
    rest_get_server()->serve_request($route);

    exit;
}

if (PHP_SAPI !== 'cli') {
    exit;
}

define('DISABLE_WP_CRON', true);

require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

add_filter('pre_wp_mail', '__return_true');

$run = 'pupilovo-cart-session-' . bin2hex(random_bytes(8));
$password = bin2hex(random_bytes(20));
$googleNonce = bin2hex(random_bytes(32));
$googleKey = openssl_pkey_new(['private_key_bits' => 2048]);

$customers = [];
$orderIds = [];
$cookieJars = [];
$sessionKeys = [];
$productId = null;
$server = null;
$log = tempnam(sys_get_temp_dir(), 'pupilovo-cart-session-log-');
$checks = 0;

function check($condition, $label)
{
    if (!$condition) {
        throw new RuntimeException("FAIL: $label");
    }

    $GLOBALS['checks']++;

    echo "PASS: $label\n";
}

function newCookieJar()
{
    $jar = tempnam(
        sys_get_temp_dir(),
        'pupilovo-cart-session-cookies-'
    );

    $GLOBALS['cookieJars'][] = $jar;

    return $jar;
}

function requestJson(
    $route,
    $jar,
    $method = 'GET',
    $body = null,
    $extraHeaders = []
) {
    $curl = curl_init(
        'http://' . $GLOBALS['address'] . '/' . ltrim($route, '/')
    );

    $headers = [
        'Content-Type: application/json',
    ];

    foreach ($extraHeaders as $name => $value) {
        $headers[] = $name . ': ' . $value;
    }

    $responseHeaders = [];

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => max(60, (int) getenv('PUPILOVO_TEST_HTTP_TIMEOUT')),
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIE => 'pupilovo_google_nonce=' . $GLOBALS['googleNonce'],
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HEADERFUNCTION => static function ($curl, $line) use (&$responseHeaders) {
            $length = strlen($line);
            $line = trim($line);

            if ($line === '' || !str_contains($line, ':')) {
                return $length;
            }

            [$name, $value] = explode(':', $line, 2);

            $responseHeaders[strtolower(trim($name))] = trim($value);

            return $length;
        },
    ]);

    if ($body !== null) {
        curl_setopt(
            $curl,
            CURLOPT_POSTFIELDS,
            wp_json_encode($body)
        );
    }

    $start = microtime(true);
    $raw = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);

    curl_close($curl);

    if ($raw === false) {
        throw new RuntimeException($error);
    }

    $data = json_decode($raw, true);

    if (!is_array($data)) {
        throw new RuntimeException(
            'Non-JSON response: ' . substr($raw, 0, 500)
        );
    }

    printf(
        "  %s %s: %d (%.2fs)\n",
        $method,
        $route,
        $status,
        microtime(true) - $start
    );

    return [$status, $data, $responseHeaders];
}

function createCart($jar)
{
    [$status, $cart, $headers] = requestJson(
        'wc/store/v1/cart',
        $jar
    );

    check(
        $status === 200,
        'guest cart created'
    );

    $token = $headers['cart-token'] ?? null;

    check(
        is_string($token) && $token !== '',
        'Cart-Token returned'
    );

    $payload = \Automattic\WooCommerce\StoreApi\Utilities\CartTokenUtils::get_cart_token_payload($token);
    $GLOBALS['sessionKeys'][] = (string) $payload['user_id'];

    return [$cart, $token];
}

function addProductToCart($jar, $token, $productId)
{
    [$status, $cart] = requestJson(
        'wc/store/v1/cart/add-item',
        $jar,
        'POST',
        [
            'id' => $productId,
            'quantity' => 1,
        ],
        [
            'Cart-Token' => $token,
        ]
    );

    check(
        in_array($status, [200, 201], true),
        'product added to cart'
    );

    check(
        ($cart['items_count'] ?? 0) === 1,
        'cart contains one item'
    );
}

function googleCredential($email)
{
    $encode = fn($value) => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    $claims = [
        'iss' => 'https://accounts.google.com', 'aud' => 'integration-client',
        'iat' => time(), 'exp' => time() + 600, 'email' => $email,
        'email_verified' => true, 'sub' => $GLOBALS['run'] . '-google',
        'given_name' => 'Test', 'nonce' => $GLOBALS['googleNonce'],
    ];
    $body = $encode(json_encode(['alg' => 'RS256', 'kid' => 'integration'])) . '.' . $encode(json_encode($claims));
    openssl_sign($body, $signature, $GLOBALS['googleKey'], OPENSSL_ALGO_SHA256);
    return $body . '.' . $encode($signature);
}

function loginCustomer($jar, $email, $password, $cartToken = null, $google = false)
{
    $headers = [];

    if ($cartToken) {
        $headers['Cart-Token'] = $cartToken;
    }

    [$status, $login] = requestJson(
        'pupilovo/v1/auth/' . ($google ? 'google' : 'login'),
        $jar,
        'POST',
        $google
            ? ['credential' => googleCredential($email), 'password' => $password]
            : ['email' => $email, 'password' => $password],
        $headers
    );

    check(
        $status === 200
        && !empty($login['authenticated']),
        'customer login'
    );

    [$status, $me] = requestJson(
        'pupilovo/v1/auth/me',
        $jar
    );

    check(
        $status === 200
        && !empty($me['authenticated'])
        && !empty($me['nonce']),
        'authenticated session available'
    );

    return [
        $me,
        isset($login['cartToken']) && is_string($login['cartToken'])
            ? $login['cartToken']
            : null,
    ];
}

function logoutCustomer($jar, $nonce)
{
    [$status] = requestJson(
        'pupilovo/v1/auth/logout',
        $jar,
        'POST',
        [],
        [
            'X-WP-Nonce' => $nonce,
        ]
    );

    check(
        $status === 200,
        'customer logout'
    );
}

function checkoutCart(
    $jar,
    $token,
    $email,
    $firstName = 'Test',
    $lastName = 'Customer',
    $nonce = null
) {
    $billing = [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'company' => '',
        'address_1' => 'Testowa 1',
        'address_2' => '',
        'city' => 'Kraków',
        'state' => '',
        'postcode' => '30-001',
        'country' => 'PL',
        'email' => $email,
        'phone' => '500500500',
    ];

    $shipping = $billing;
    unset($shipping['email'], $shipping['phone']);

    [$status, $checkout] = requestJson(
        'wc/store/v1/checkout',
        $jar,
        'POST',
        [
            'billing_address' => $billing,
            'shipping_address' => $shipping,
            'customer_note' => '',
            'payment_method' => 'cod',
            'payment_data' => [],
        ],
        [
            'Cart-Token' => $token,
        ] + ($nonce ? ['X-WP-Nonce' => $nonce] : [])
    );

    check(
        $status === 200,
        'checkout completed: ' . ($checkout['message'] ?? (string) $status)
    );

    $orderId = (int) ($checkout['order_id'] ?? 0);

    check(
        $orderId > 0,
        'checkout returned order id'
    );

    $GLOBALS['orderIds'][] = $orderId;

    $order = wc_get_order($orderId);

    check(
        $order instanceof WC_Order,
        'created order can be loaded'
    );

    return $order;
}

try {
    foreach (['owner', 'other'] as $name) {
        $email = "$run-$name@example.com";

        $id = wc_create_new_customer(
            $email,
            '',
            $password
        );

        if (is_wp_error($id)) {
            throw new RuntimeException(
                'Fixture customer creation failed: '
                . $id->get_error_message()
            );
        }

        $customers[$name] = $id;

        echo "Prepared customer fixture: $name id=$id\n";
    }

    $product = new WC_Product_Simple();

    $product->set_name('Pupilovo cart session test product');
    $product->set_status('publish');
    $product->set_regular_price('10.00');
    $product->set_price('10.00');
    $product->set_virtual(true);
    $product->set_stock_status('instock');

    $productId = $product->save();

    if (!$productId) {
        throw new RuntimeException(
            'Fixture product creation failed'
        );
    }

    echo "Prepared product fixture: id=$productId\n";

    $socket = stream_socket_server(
        'tcp://127.0.0.1:0',
        $errno,
        $error
    );

    if (!$socket) {
        throw new RuntimeException($error);
    }

    $address = stream_socket_get_name(
        $socket,
        false
    );

    fclose($socket);

    $env = getenv();
    $env['PUPILOVO_CART_SESSION_TEST'] = '1';
    $env['PUPILOVO_GOOGLE_CLIENT_ID'] = 'integration-client';
    $env['PUPILOVO_CART_GOOGLE_KEY'] = openssl_pkey_get_details($googleKey)['key'];

    $server = proc_open(
        [
            PHP_BINARY,
            '-S',
            $address,
            __FILE__,
        ],
        [
            0 => ['pipe', 'r'],
            1 => ['file', $log, 'a'],
            2 => ['file', $log, 'a'],
        ],
        $pipes,
        null,
        $env
    );

    for ($i = 0; $i < 50; $i++) {
        $ready = @stream_socket_client(
            'tcp://' . $address,
            $errno,
            $error,
            0.1
        );

        if ($ready) {
            fclose($ready);
            break;
        }

        usleep(100000);
    }

    /*
     * Scenario 1:
     * guest cart -> owner login -> checkout.
     */
    echo "\nScenario 1: guest cart -> login -> checkout\n";

    $jar = newCookieJar();

    [, $cartToken] = createCart($jar);

    addProductToCart(
        $jar,
        $cartToken,
        $productId
    );

    $guestCartToken = $cartToken;

    [$ownerSession, $cartToken] = loginCustomer(
        $jar,
        "$run-owner@example.com",
        $password,
        $guestCartToken
    );

    check(
        is_string($cartToken) && $cartToken !== '',
        'authenticated Cart-Token returned'
    );

    check(
        $cartToken !== $guestCartToken,
        'guest Cart-Token rotated after login'
    );

    [$status, $cartAfterLogin] = requestJson(
        'wc/store/v1/cart',
        $jar,
        'GET',
        null,
        ['Cart-Token' => $cartToken, 'X-WP-Nonce' => $ownerSession['nonce']]
    );

    check(
        $status === 200
        && ($cartAfterLogin['items_count'] ?? 0) === 1,
        'cart preserved after login'
    );

    $order = checkoutCart(
        $jar,
        $cartToken,
        "$run-owner@example.com",
        nonce: $ownerSession['nonce']
    );

    check(
        $order->get_customer_id() === $customers['owner'],
        'guest cart checkout assigned to logged-in owner'
    );

    /*
     * Scenario 2:
     * pure guest checkout, even when billing email belongs
     * to an existing customer.
     */
    echo "\nScenario 2: guest checkout stays guest\n";

    $jar = newCookieJar();

    [, $cartToken] = createCart($jar);

    addProductToCart(
        $jar,
        $cartToken,
        $productId
    );

    $order = checkoutCart(
        $jar,
        $cartToken,
        "$run-owner@example.com"
    );

    check(
        $order->get_customer_id() === 0,
        'guest checkout has customer_id=0'
    );

    check(
        $order->get_billing_email() === "$run-owner@example.com",
        'existing customer email alone does not assign customer'
    );

    /*
     * Scenario 3:
     * guest cart -> owner login -> logout -> other login
     * -> fresh cart, matching the frontend token reset -> checkout.
     */
    echo "\nScenario 3: account switching does not leak identity\n";

    $jar = newCookieJar();

    [, $cartToken] = createCart($jar);

    addProductToCart(
        $jar,
        $cartToken,
        $productId
    );

    [$ownerSession] = loginCustomer(
        $jar,
        "$run-owner@example.com",
        $password
    );

    logoutCustomer(
        $jar,
        $ownerSession['nonce']
    );

    // The frontend clears its Cart-Token on logout.
    [, $cartToken] = createCart($jar);
    addProductToCart($jar, $cartToken, $productId);

    [$otherSession, $cartToken] = loginCustomer(
        $jar,
        "$run-other@example.com",
        $password,
        $cartToken
    );

    /*
     * Deliberately keep owner's billing email.
     * Identity must come from authenticated session,
     * not from checkout email.
     */
    $order = checkoutCart(
        $jar,
        $cartToken,
        "$run-owner@example.com",
        nonce: $otherSession['nonce']
    );

    check(
        $order->get_customer_id() === $customers['other'],
        'checkout assigned only to second logged-in customer'
    );

    check(
        $order->get_customer_id() !== $customers['owner'],
        'previous customer identity did not leak'
    );

    echo "\nScenario 4: guest cart -> existing Google flow -> checkout\n";
    $jar = newCookieJar();
    [, $guestToken] = createCart($jar);
    addProductToCart($jar, $guestToken, $productId);
    [$googleSession, $googleToken] = loginCustomer($jar, "$run-owner@example.com", $password, $guestToken, true);
    check($googleSession['user']['id'] === $customers['owner'], 'Google linking preserves customer ID');
    check(is_string($googleToken) && $googleToken !== $guestToken, 'Google login rotates guest token');
    $order = checkoutCart($jar, $googleToken, "$run-owner@example.com", nonce: $googleSession['nonce']);
    check($order->get_customer_id() === $customers['owner'], 'Google guest cart checkout assigned to existing owner');

    echo "\nScenario 5: expired WordPress session\n";
    WP_Session_Tokens::get_instance($customers['owner'])->destroy_all();
    [$status, $expired] = requestJson('pupilovo/v1/auth/me', $jar);
    check($status === 200 && $expired['authenticated'] === false && $expired['nonce'] === null, 'expired cookie is anonymous in auth bootstrap');
    [$status] = requestJson('pupilovo/v1/account/orders', $jar, 'GET', null, ['X-WP-Nonce' => $googleSession['nonce']]);
    check(in_array($status, [401, 403], true), 'expired session cannot read account orders');

    echo "\n{$checks} customer/cart session integration checks passed\n";
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }

    foreach ($orderIds as $id) {
        $order = wc_get_order($id);

        if ($order) {
            $order->delete(true);
        }
    }

    if ($productId) {
        $product = wc_get_product($productId);

        if ($product) {
            $product->delete(true);
        }
    }

    foreach ($customers as $id) {
        $user = get_user_by('id', $id);

        if (
            $user
            && str_starts_with(
                $user->user_email,
                $run . '-'
            )
        ) {
            wp_delete_user($id);
        }
    }

    global $wpdb;
    foreach (array_unique(array_merge($sessionKeys, array_values($customers))) as $key) {
        $wpdb->delete($wpdb->prefix . 'woocommerce_sessions', ['session_key' => (string) $key], ['%s']);
    }

    foreach ($cookieJars as $jar) {
        if (is_file($jar)) {
            unlink($jar);
        }
    }

    if (is_file($log)) {
        unlink($log);
    }

    echo "Test orders, product, customers and sessions cleaned up.\n";
}
