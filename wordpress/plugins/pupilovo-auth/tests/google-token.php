<?php
// Run only with CLI: php tests/google-token.php (no WordPress or network required).
if (PHP_SAPI !== 'cli') { exit; }
define('ABSPATH', __DIR__);
function add_action(...$args) {}
class WP_Error {
    public function __construct(public string $code, ...$args) {}
}
function is_email($email) { return filter_var($email, FILTER_VALIDATE_EMAIL); }
function get_transient($key) { return $GLOBALS['certs']; }
require dirname(__DIR__) . '/pupilovo-auth.php';
putenv('PUPILOVO_GOOGLE_CLIENT_ID=test-client');
$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
$GLOBALS['certs'] = ['test-key' => openssl_pkey_get_details($key)['key']];
$_COOKIE['pupilovo_google_nonce'] = str_repeat('a', 64);
$claims = [
    'iss' => 'https://accounts.google.com', 'aud' => 'test-client',
    'exp' => time() + 300, 'iat' => time(), 'email' => 'customer@example.com',
    'email_verified' => true, 'sub' => 'google-subject', 'nonce' => str_repeat('a', 64),
];
function encode_part($value) { return rtrim(strtr(base64_encode($value), '+/', '-_'), '='); }
function token($claims, $header = ['alg' => 'RS256', 'kid' => 'test-key']) {
    $body = encode_part(json_encode($header)) . '.' . encode_part(json_encode($claims));
    openssl_sign($body, $signature, $GLOBALS['key'], OPENSSL_ALGO_SHA256);
    return $body . '.' . encode_part($signature);
}
$count = 0;
function check($name, $token, $code = null) {
    $result = pupilovo_verify_google_token($token);
    $actual = $result instanceof WP_Error ? $result->code : null;
    if ($actual !== $code) { throw new RuntimeException("FAIL: $name ($actual)"); }
    $GLOBALS['count']++;
    echo "PASS: $name\n";
}
check('valid RSA signature and claims', token($claims));
foreach ([
    'iss' => 'https://attacker.example', 'aud' => 'other-client', 'azp' => 'other-client',
    'exp' => time() - 1, 'iat' => time() + 600, 'email' => 'invalid',
    'email_verified' => false, 'sub' => '',
] as $field => $value) {
    check("reject $field", token(array_replace($claims, [$field => $value])), 'invalid_google_token');
}
check('reject string email_verified', token(array_replace($claims, ['email_verified' => 'true'])), 'invalid_google_token');
check('reject absent sub', token(array_diff_key($claims, ['sub' => true])), 'invalid_google_token');
check('reject algorithm substitution', token($claims, ['alg' => 'HS256', 'kid' => 'test-key']), 'invalid_google_token');
check('reject unknown signing key', token($claims, ['alg' => 'RS256', 'kid' => 'unknown']), 'invalid_google_token');
$parts = explode('.', token($claims));
$parts[1] = encode_part(json_encode(array_replace($claims, ['sub' => 'attacker'])));
check('reject tampered signed payload', implode('.', $parts), 'invalid_google_token');
check('reject malformed JWT', 'bad.token', 'invalid_google_token');
check('reject browser nonce mismatch', token(array_replace($claims, ['nonce' => str_repeat('b', 64)])), 'google_csrf');
unset($_COOKIE['pupilovo_google_nonce']);
check('reject missing browser cookie', token($claims), 'google_csrf');
putenv('PUPILOVO_GOOGLE_CLIENT_ID=');
check('controlled configuration error', token($claims), 'google_unconfigured');
echo "$count checks passed\n";

// Exercise customer linking and session creation using an in-memory WordPress adapter.
function is_wp_error($value) { return $value instanceof WP_Error; }
class WP_User {
    public $roles = ['customer'];
    public function __construct(public $ID, public $user_email, public $user_login = 'customer') {}
}
class WP_REST_Request {
    public function __construct(private $credential, private $password = null) {}
    public function get_param($key) { return $key === 'password' ? $this->password : $this->credential; }
}
class WP_REST_Response {
    public function __construct(public $data) {}
}
class TestDatabase {
    public $prefix = 'test_';
    public function prepare($sql, ...$args) { return $sql; }
    public function get_var($sql) { return 1; }
}
$wpdb = new TestDatabase();
$users = []; $meta = []; $cookie_user = null;
function get_users($query) {
    return array_values(array_filter($GLOBALS['users'], fn($u) => get_user_meta($u->ID, 'pupilovo_google_sub', true) === $query['meta_value']));
}
function get_user_by($field, $value) {
    foreach ($GLOBALS['users'] as $user) {
        if (($field === 'id' ? $user->ID : $user->user_email) === $value) return $user;
    }
    return false;
}
function get_user_meta($id, $key, $single) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function update_user_meta($id, $key, $value) { $GLOBALS['meta'][$id][$key] = $value; }
function user_can($user, $cap) { return in_array('administrator', $user->roles, true); }
function sanitize_text_field($value) { return strip_tags($value); }
function wp_generate_password(...$args) { return 'generated-test-password'; }
function wc_create_new_customer($email, $login, $password, $data) {
    $id = count($GLOBALS['users']) + 1;
    $GLOBALS['users'][$id] = new WP_User($id, $email);
    update_user_meta($id, 'first_name', $data['first_name']);
    return $id;
}
function wp_set_current_user($id) {}
function wc_set_customer_auth_cookie($id) { $GLOBALS['cookie_user'] = $id; }
function do_action(...$args) {}
function login_check($name, $claims, $expected_id = null, $error = null, $password = null) {
    $GLOBALS['cookie_user'] = null;
    $result = pupilovo_login_google(new WP_REST_Request(token($claims), $password));
    if ($error) {
        $ok = $result instanceof WP_Error && $result->code === $error && $GLOBALS['cookie_user'] === null;
    } else {
        $ok = $result instanceof WP_REST_Response && $result->data['user']['id'] === $expected_id
            && $result->data['nonce'] === null && $GLOBALS['cookie_user'] === $expected_id;
    }
    if (!$ok) throw new RuntimeException("FAIL: $name");
    echo "PASS: $name\n";
}
putenv('PUPILOVO_GOOGLE_CLIENT_ID=test-client');
$_COOKIE['pupilovo_google_nonce'] = str_repeat('a', 64);
login_check('create customer and session', $claims, 1);
login_check('repeat login reuses subject despite changed email', array_replace($claims, ['email' => 'changed@example.com']), 1);
$users[2] = new WP_User(2, 'existing@example.com');
login_check('require confirmation for third-party mailbox', array_replace($claims, ['sub' => 'second', 'email' => 'existing@example.com']), null, 'google_link_confirmation_required');
login_check('reject incorrect confirmation password', array_replace($claims, ['sub' => 'second', 'email' => 'existing@example.com']), null, 'google_link_confirmation_required', 'wrong');
login_check('link existing customer by verified email', array_replace($claims, ['sub' => 'second', 'email' => 'existing@example.com']), 2, null, 'correct');
login_check('reject replacement of linked subject', array_replace($claims, ['sub' => 'third', 'email' => 'existing@example.com']), null, 'google_link_conflict');
$users[3] = new WP_User(3, 'admin@example.com'); $users[3]->roles = ['administrator'];
login_check('reject admin linking', array_replace($claims, ['email' => 'admin@example.com', 'sub' => 'admin']), null, 'google_customer_only');

function wp_authenticate($login, $password) { return $password === 'correct' ? $GLOBALS['users'][2] : new WP_Error('invalid_password'); }
