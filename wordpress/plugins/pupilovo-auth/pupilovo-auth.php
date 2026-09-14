<?php

/**
 * Plugin Name: Pupilovo Auth
 * Description: REST API authentication for the Pupilovo React frontend.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/customer-orders.php';

add_action('rest_api_init', function () {
    register_rest_route('pupilovo/v1', '/auth/register', [
        'methods' => 'POST',
        'callback' => 'pupilovo_register_customer',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('pupilovo/v1', '/auth/login', [
        'methods' => 'POST',
        'callback' => 'pupilovo_login_customer',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('pupilovo/v1', '/auth/google', [
        'methods' => 'POST',
        'callback' => 'pupilovo_login_google',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('pupilovo/v1', '/auth/me', [
        'methods' => 'GET',
        'callback' => 'pupilovo_get_current_customer',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('pupilovo/v1', '/auth/logout', [
        'methods' => 'POST',
        'callback' => 'pupilovo_logout_customer',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
    ]);
});

function pupilovo_get_user_payload(WP_User $user): array
{
    return [
        'id' => $user->ID,
        'firstName' => get_user_meta($user->ID, 'first_name', true),
        'email' => $user->user_email,
    ];
}

function pupilovo_auth_response(
    WP_User $user,
    bool $include_nonce = true,
    ?string $cart_token = null
): WP_REST_Response {
    return new WP_REST_Response([
        'authenticated' => true,
        'user' => pupilovo_get_user_payload($user),
        'nonce' => $include_nonce
            ? wp_create_nonce('wp_rest')
            : null,
        'cartToken' => $cart_token,
    ]);
}

function pupilovo_migrate_cart_session(
    WP_REST_Request $request,
    int $user_id
): ?string {
    $cart_token_class =
        '\Automattic\WooCommerce\StoreApi\Utilities\CartTokenUtils';

    if (!class_exists($cart_token_class)) {
        return null;
    }

    $incoming_token = trim(
        (string) $request->get_header('Cart-Token')
    );

    if (
        $incoming_token !== ''
        && $cart_token_class::validate_cart_token($incoming_token)
    ) {
        $payload = $cart_token_class::get_cart_token_payload(
            $incoming_token
        );

        $source_session_id = (string) ($payload['user_id'] ?? '');

        if (
            $source_session_id !== ''
            && str_starts_with($source_session_id, 't_')
        ) {
            global $wpdb;

            $table = $wpdb->prefix . 'woocommerce_sessions';

            $session = $wpdb->get_row(
                $wpdb->prepare(
                    'SELECT session_value
                     FROM %i
                     WHERE session_key = %s',
                    $table,
                    $source_session_id
                ),
                ARRAY_A
            );

            if ($session && isset($session['session_value'])) {
                $expiration =
                    $cart_token_class::get_cart_token_expiration();

                $saved = $wpdb->query(
                    $wpdb->prepare(
                        'INSERT INTO %i
                            (`session_key`, `session_value`, `session_expiry`)
                         VALUES (%s, %s, %d)
                         ON DUPLICATE KEY UPDATE
                            `session_value` = VALUES(`session_value`),
                            `session_expiry` = VALUES(`session_expiry`)',
                        $table,
                        (string) $user_id,
                        $session['session_value'],
                        $expiration
                    )
                );

                if ($saved !== false) {
                    $wpdb->delete(
                        $table,
                        ['session_key' => $source_session_id],
                        ['%s']
                    );

                    do_action(
                        'woocommerce_guest_session_to_user_id',
                        $source_session_id,
                        (string) $user_id
                    );
                }
            }
        }
    }

    return $cart_token_class::get_cart_token(
        (string) $user_id
    );
}

function pupilovo_finish_auth(
    WP_User $user,
    WP_REST_Request $request
): WP_REST_Response {
    return pupilovo_auth_response(
        $user,
        false,
        pupilovo_migrate_cart_session(
            $request,
            $user->ID
        )
    );
}

/**
 * Restore the WordPress user from the logged-in cookie.
 *
 * REST cookie authentication treats requests without X-WP-Nonce as
 * anonymous. This endpoint is used to bootstrap the REST nonce after
 * login and after a browser refresh.
 */
function pupilovo_restore_user_from_cookie(): ?WP_User
{
    if (is_user_logged_in()) {
        return wp_get_current_user();
    }

    if (empty($_COOKIE[LOGGED_IN_COOKIE])) {
        return null;
    }

    $cookie = wp_unslash($_COOKIE[LOGGED_IN_COOKIE]);

    $user_id = wp_validate_auth_cookie(
        $cookie,
        'logged_in'
    );

    if (!$user_id) {
        return null;
    }

    wp_set_current_user($user_id);

    $user = wp_get_current_user();

    return $user->exists() ? $user : null;
}

function pupilovo_register_customer(WP_REST_Request $request)
{
    if (!function_exists('wc_create_new_customer')) {
        return new WP_Error(
            'woocommerce_unavailable',
            'WooCommerce nie jest dostępny.',
            ['status' => 500]
        );
    }

    $first_name = sanitize_text_field(
        (string) $request->get_param('firstName')
    );

    $email = sanitize_email(
        (string) $request->get_param('email')
    );

    $password = (string) $request->get_param('password');

    if ($first_name === '') {
        return new WP_Error(
            'missing_first_name',
            'Podaj imię.',
            ['status' => 400]
        );
    }

    if (!is_email($email)) {
        return new WP_Error(
            'invalid_email',
            'Podaj prawidłowy adres e-mail.',
            ['status' => 400]
        );
    }

    if (strlen($password) < 8) {
        return new WP_Error(
            'password_too_short',
            'Hasło musi mieć co najmniej 8 znaków.',
            ['status' => 400]
        );
    }

    if (email_exists($email)) {
        return new WP_Error(
            'email_exists',
            'Konto z tym adresem e-mail już istnieje.',
            ['status' => 409]
        );
    }

    $customer_id = wc_create_new_customer(
        $email,
        '',
        $password,
        [
            'first_name' => $first_name,
            'display_name' => $first_name,
        ]
    );

    if (is_wp_error($customer_id)) {
        return new WP_Error(
            $customer_id->get_error_code(),
            $customer_id->get_error_message(),
            ['status' => 400]
        );
    }

    update_user_meta(
        $customer_id,
        'first_name',
        $first_name
    );

    wc_set_customer_auth_cookie($customer_id);

    $user = get_user_by('id', $customer_id);

    if (!$user) {
        return new WP_Error(
            'user_not_found',
            'Nie udało się pobrać utworzonego użytkownika.',
            ['status' => 500]
        );
    }

    /*
     * Cookie zostanie zapisane przez przeglądarkę dopiero po zakończeniu
     * tego requestu. Dlatego nonce pobieramy później przez /auth/me.
     */
    return pupilovo_finish_auth($user, $request);
}

function pupilovo_login_customer(WP_REST_Request $request)
{
    $email = sanitize_email(
        (string) $request->get_param('email')
    );

    $password = (string) $request->get_param('password');

    if (!is_email($email) || $password === '') {
        return new WP_Error(
            'invalid_credentials',
            'Podaj poprawny e-mail i hasło.',
            ['status' => 400]
        );
    }

    $user = get_user_by('email', $email);

    if (!$user) {
        return new WP_Error(
            'invalid_credentials',
            'Nieprawidłowy e-mail lub hasło.',
            ['status' => 401]
        );
    }

    $authenticated_user = wp_signon(
        [
            'user_login' => $user->user_login,
            'user_password' => $password,
            'remember' => true,
        ],
        is_ssl()
    );

    if (is_wp_error($authenticated_user)) {
        return new WP_Error(
            'invalid_credentials',
            'Nieprawidłowy e-mail lub hasło.',
            ['status' => 401]
        );
    }

    wp_set_current_user($authenticated_user->ID);

    /*
     * Nonce bootstrapujemy dopiero przez kolejne /auth/me.
     */
    return pupilovo_finish_auth(
        $authenticated_user,
        $request
    );
}

function pupilovo_get_current_customer()
{
    $user = pupilovo_restore_user_from_cookie();

    if (!$user) {
        return new WP_REST_Response([
            'authenticated' => false,
            'user' => null,
            'nonce' => null,
        ]);
    }

    return pupilovo_auth_response($user);
}

function pupilovo_logout_customer()
{
    wp_logout();

    return new WP_REST_Response([
        'authenticated' => false,
        'user' => null,
        'nonce' => null,
    ]);
}

/** Only RS256 and keys fetched from Google's fixed HTTPS endpoint are accepted. */
function pupilovo_verify_google_token(string $credential)
{
    $client_id = trim((string) getenv('PUPILOVO_GOOGLE_CLIENT_ID'));
    if ($client_id === '' || !function_exists('openssl_verify')) {
        return new WP_Error('google_unconfigured', 'Logowanie Google nie jest skonfigurowane.', ['status' => 503]);
    }
    $invalid = new WP_Error('invalid_google_token', 'Nieprawidłowy lub wygasły token Google.', ['status' => 401]);
    $parts = explode('.', $credential);
    if (count($parts) !== 3 || strlen($credential) > 16384) {
        return $invalid;
    }
    $decoded = [];
    foreach ($parts as $part) {
        if (!preg_match('/^[A-Za-z0-9_-]+$/D', $part)) {
            return $invalid;
        }
        $value = base64_decode(strtr($part, '-_', '+/'), true);
        if ($value === false) {
            return $invalid;
        }
        $decoded[] = $value;
    }
    $header = json_decode($decoded[0], true);
    $claims = json_decode($decoded[1], true);
    if (!is_array($header) || !is_array($claims)
        || ($header['alg'] ?? '') !== 'RS256'
        || !is_string($header['kid'] ?? null) || isset($header['crit'])) {
        return $invalid;
    }

    $certs = get_transient('pupilovo_google_certs');
    if (!is_array($certs)) {
        $response = wp_remote_get('https://www.googleapis.com/oauth2/v1/certs', [
            'timeout' => 10,
            'redirection' => 0,
            'limit_response_size' => 65536,
        ]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return new WP_Error('google_unavailable', 'Nie można teraz zweryfikować Google. Spróbuj ponownie.', ['status' => 503]);
        }
        $certs = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($certs) || !$certs) {
            return new WP_Error('google_unavailable', 'Nie można teraz zweryfikować Google.', ['status' => 503]);
        }
        $ttl = 300;
        if (preg_match('/max-age=(\d+)/', (string) wp_remote_retrieve_header($response, 'cache-control'), $match)) {
            $ttl = max(1, min(86400, (int) $match[1] - (int) wp_remote_retrieve_header($response, 'age')));
        }
        set_transient('pupilovo_google_certs', $certs, $ttl);
    }
    $cert = $certs[$header['kid']] ?? null;
    if (!is_string($cert) || openssl_verify($parts[0] . '.' . $parts[1], $decoded[2], $cert, OPENSSL_ALGO_SHA256) !== 1) {
        return $invalid;
    }
    if (!in_array($claims['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true)
        || ($claims['aud'] ?? null) !== $client_id
        || (isset($claims['azp']) && $claims['azp'] !== $client_id)
        || !is_int($claims['exp'] ?? null) || $claims['exp'] <= time()
        || !is_int($claims['iat'] ?? null) || $claims['iat'] > time() + 60
        || ($claims['email_verified'] ?? null) !== true
        || !is_string($claims['email'] ?? null) || !is_email($claims['email'])
        || !is_string($claims['sub'] ?? null) || $claims['sub'] === '' || strlen($claims['sub']) > 255) {
        return $invalid;
    }
    // Bind the signed credential to the browser that started GIS (login CSRF protection).
    $nonce = $_COOKIE['pupilovo_google_nonce'] ?? '';
    if (!is_string($nonce) || !preg_match('/^[a-f0-9]{64}$/D', $nonce)
        || !is_string($claims['nonce'] ?? null) || !hash_equals($nonce, $claims['nonce'])) {
        return new WP_Error('google_csrf', 'Sesja Google wygasła. Odśwież stronę i spróbuj ponownie.', ['status' => 403]);
    }
    return $claims;
}

function pupilovo_login_google(WP_REST_Request $request)
{
    $credential = $request->get_param('credential');
    if (!is_string($credential) || $credential === '') {
        return new WP_Error('missing_credential', 'Brak tokenu Google.', ['status' => 400]);
    }
    $claims = pupilovo_verify_google_token($credential);
    if (is_wp_error($claims)) {
        return $claims;
    }
    if (!function_exists('wc_create_new_customer') || !function_exists('wc_set_customer_auth_cookie')) {
        return new WP_Error('woocommerce_unavailable', 'WooCommerce nie jest dostępny.', ['status' => 503]);
    }
    // Serialize Google linking/creation to avoid concurrent duplicate subject bindings.
    global $wpdb;
    $lock = 'pupilovo_google_' . md5($wpdb->prefix);
    if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock)) !== 1) {
        return new WP_Error('google_busy', 'Spróbuj zalogować się ponownie za chwilę.', ['status' => 503]);
    }
    try {
        $users = get_users([
            'meta_key' => 'pupilovo_google_sub',
            'meta_value' => $claims['sub'],
            'number' => 2,
        ]);
        // MySQL meta comparisons can be case-insensitive; subjects must match exactly.
        $user = $users[0] ?? false;
        if (count($users) > 1 || ($user && get_user_meta($user->ID, 'pupilovo_google_sub', true) !== $claims['sub'])) {
            return new WP_Error('google_link_conflict', 'Nie można powiązać konta Google.', ['status' => 409]);
        }
        if (!$user) {
            $user = get_user_by('email', $claims['email']);
        }
        if ($user && (count($user->roles) !== 1 || !in_array('customer', $user->roles, true) || user_can($user, 'manage_options'))) {
            return new WP_Error('google_customer_only', 'Dla tego konta użyj logowania e-mail i hasłem.', ['status' => 403]);
        }
        if ($user && get_user_meta($user->ID, 'pupilovo_google_sub', true) === ''
            && !str_ends_with(strtolower($claims['email']), '@gmail.com')
            && !(is_string($claims['hd'] ?? null) && $claims['hd'] !== '')) {
            // For third-party mailboxes Google cannot establish current mailbox ownership.
            $password = $request->get_param('password');
            if (!is_string($password) || $password === '') {
                return new WP_Error('google_link_confirmation_required', 'Potwierdź hasło do istniejącego konta Pupilovo, aby połączyć je z Google.', ['status' => 403]);
            }
            $confirmed = wp_authenticate($user->user_login, $password);
            if (is_wp_error($confirmed) || $confirmed->ID !== $user->ID) {
                return new WP_Error('google_link_confirmation_required', 'Nieprawidłowe hasło do konta Pupilovo.', ['status' => 403]);
            }
        }
        if (!$user) {
            $first_name = is_string($claims['given_name'] ?? null) ? sanitize_text_field($claims['given_name']) : '';
            $name = is_string($claims['name'] ?? null) ? sanitize_text_field($claims['name']) : $first_name;
            $id = wc_create_new_customer($claims['email'], '', wp_generate_password(32, true, true), [
                'first_name' => $first_name,
                'display_name' => $name ?: $first_name,
                'role' => 'customer',
            ]);
            if (is_wp_error($id)) {
                return new WP_Error('google_create_failed', 'Nie udało się utworzyć konta. Spróbuj ponownie.', ['status' => 409]);
            }
            $user = get_user_by('id', $id);
            if (!$user) {
                return new WP_Error('google_create_failed', 'Nie udało się pobrać konta.', ['status' => 500]);
            }
        }
        $subject = get_user_meta($user->ID, 'pupilovo_google_sub', true);
        if ($subject !== '' && $subject !== $claims['sub']) {
            return new WP_Error('google_link_conflict', 'Konto jest powiązane z innym kontem Google. Użyj e-maila i hasła.', ['status' => 409]);
        }
        if ($subject === '') {
            update_user_meta($user->ID, 'pupilovo_google_sub', $claims['sub']);
            if (get_user_meta($user->ID, 'pupilovo_google_sub', true) !== $claims['sub']) {
                return new WP_Error('google_link_failed', 'Nie udało się powiązać konta Google.', ['status' => 500]);
            }
        }
    } finally {
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
    }
    wp_set_current_user($user->ID);
    wc_set_customer_auth_cookie($user->ID);
    do_action('wp_login', $user->user_login, $user);
    return pupilovo_finish_auth($user, $request);
}
