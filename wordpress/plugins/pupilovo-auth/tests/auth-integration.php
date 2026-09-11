<?php
/** CLI-only integration suite. Uses a temporary loopback HTTP server and disposable customers. */
if (PHP_SAPI === 'cli-server' && getenv('PUPILOVO_TEST_PUBLIC_KEY')) {
    define('DISABLE_WP_CRON', true);
    require '/var/www/html/wp-load.php';
    add_filter('pre_wp_mail', '__return_true');
    add_filter('pre_transient_pupilovo_google_certs', fn() => ['integration' => getenv('PUPILOVO_TEST_PUBLIC_KEY')]);
    rest_get_server()->serve_request(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    exit;
}
if (PHP_SAPI !== 'cli') { exit; }
$key = openssl_pkey_new(['private_key_bits' => 2048]);
$run = 'pupilovo-test-' . bin2hex(random_bytes(8));
$nonce = bin2hex(random_bytes(32));
$password = bin2hex(random_bytes(20));
$env = getenv();
$env['PUPILOVO_TEST_PUBLIC_KEY'] = openssl_pkey_get_details($key)['key'];
$env['PUPILOVO_GOOGLE_CLIENT_ID'] = 'integration-client';
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
$address = stream_socket_get_name($socket, false);
fclose($socket);
$log = tempnam(sys_get_temp_dir(), 'pupilovo-auth-log-');
$server = proc_open([PHP_BINARY, '-S', $address, __FILE__], [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, null, $env);
$jar = tempnam(sys_get_temp_dir(), 'pupilovo-cookies-');
$emails = ["$run@example.com", "$run@gmail.com", "$run-new@example.com"];
$checks = 0;
function check($condition, $label) {
    if (!$condition) { throw new RuntimeException("FAIL: $label"); }
    $GLOBALS['checks']++;
    echo "PASS: $label\n";
}
function request($endpoint, $body = null, $nonceHeader = null) {
    $c = curl_init('http://' . $GLOBALS['address'] . '/pupilovo/v1/auth/' . $endpoint);
    $headers = ['Content-Type: application/json'];
    if ($nonceHeader !== null) $headers[] = 'X-WP-Nonce: ' . $nonceHeader;
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_COOKIEFILE => $GLOBALS['jar'], CURLOPT_COOKIEJAR => $GLOBALS['jar'],
        CURLOPT_COOKIE => 'pupilovo_google_nonce=' . $GLOBALS['nonce'], CURLOPT_HTTPHEADER => $headers]);
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
function credential($email, $sub) {
    $encode = fn($s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    $claims = ['iss' => 'https://accounts.google.com', 'aud' => 'integration-client',
        'iat' => time(), 'exp' => time() + 600, 'email' => $email, 'email_verified' => true,
        'sub' => $sub, 'given_name' => 'Auth Test', 'name' => 'Auth Test', 'nonce' => $GLOBALS['nonce']];
    $body = $encode(json_encode(['alg' => 'RS256', 'kid' => 'integration'])) . '.' . $encode(json_encode($claims));
    openssl_sign($body, $signature, $GLOBALS['key'], OPENSSL_ALGO_SHA256);
    return $body . '.' . $encode($signature);
}
function session_and_logout($id) {
    [$status, $me] = request('me');
    check($status === 200 && $me['user']['id'] === $id && !empty($me['nonce']), 'cookie restores user and REST nonce');
    [, $refresh] = request('me');
    check($refresh['user']['id'] === $id, 'refresh preserves session');
    [$status] = request('logout', [], 'invalid');
    check($status === 403, 'logout rejects invalid REST nonce');
    [$status, $logout] = request('logout', [], $me['nonce']);
    check($status === 200 && !$logout['authenticated'], 'logout accepts real REST nonce');
    [, $me] = request('me');
    check(!$me['authenticated'], 'logout clears session');
}
try {
    for ($i = 0; $i < 50; $i++) {
        $ready = @stream_socket_client('tcp://' . $address, $errno, $error, .1);
        if ($ready) { fclose($ready); break; }
        usleep(100000);
    }
    [$status, $registered] = request('register', ['firstName' => 'Auth Test', 'email' => $emails[0], 'password' => $password]);
    check($status === 200 && $registered['authenticated'], 'classic registration');
    $id = $registered['user']['id'];
    session_and_logout($id);
    [$status] = request('login', ['email' => $emails[0], 'password' => 'wrong']);
    check($status === 401, 'classic login rejects wrong password');
    [$status, $login] = request('login', ['email' => $emails[0], 'password' => $password]);
    check($status === 200 && $login['user']['id'] === $id, 'classic login succeeds');
    session_and_logout($id);
    $token = credential($emails[0], "$run-sub");
    [$status, $result] = request('google', ['credential' => $token]);
    check($status === 403 && $result['code'] === 'google_link_confirmation_required', 'third-party mailbox needs password');
    [$status] = request('google', ['credential' => $token, 'password' => 'wrong']);
    check($status === 403, 'wrong linking password rejected');
    [$status, $linked] = request('google', ['credential' => $token, 'password' => $password]);
    check($status === 200 && $linked['user']['id'] === $id, 'link keeps existing customer ID');
    session_and_logout($id);
    [$status, $linked] = request('google', ['credential' => $token]);
    check($status === 200 && $linked['user']['id'] === $id, 'linked Google account needs no password on repeat login');
    session_and_logout($id);
    [$status, $registered] = request('register', ['firstName' => 'Auth Test', 'email' => $emails[1], 'password' => $password]);
    check($status === 200, 'Gmail fixture registered');
    $gmailId = $registered['user']['id'];
    session_and_logout($gmailId);
    [$status, $linked] = request('google', ['credential' => credential($emails[1], "$run-gmail")]);
    check($status === 200 && $linked['user']['id'] === $gmailId, 'verified Gmail links without duplicate');
    session_and_logout($gmailId);
    [$status, $created] = request('google', ['credential' => credential($emails[2], "$run-new")]);
    check($status === 200 && $created['authenticated'], 'Google creates new customer');
    session_and_logout($created['user']['id']);
    [$status] = request('login', ['email' => $emails[0], 'password' => $password]);
    check($status === 200, 'classic password still works after Google linking');
    session_and_logout($id);
    echo "$checks integration checks passed\n";
} finally {
    proc_terminate($server);
    proc_close($server);
    // Delete only the exact uniquely named fixtures owned by this run.
    define('DISABLE_WP_CRON', true);
    require '/var/www/html/wp-load.php';
    require_once ABSPATH . 'wp-admin/includes/user.php';
    add_filter('pre_wp_mail', '__return_true');
    foreach ($emails as $email) {
        $user = get_user_by('email', $email);
        if ($user && $user->user_email === $email) wp_delete_user($user->ID);
    }
    unlink($jar);
    unlink($log);
    echo "Temporary test customers and server cleaned up.\n";
}
