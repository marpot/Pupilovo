<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    register_rest_route('pupilovo/v1', '/account/addresses', [
        'methods' => 'GET',
        'callback' => 'pupilovo_get_customer_addresses',
        'permission_callback' => 'is_user_logged_in',
    ]);
    register_rest_route('pupilovo/v1', '/account/addresses/(?P<type>billing|shipping)', [
        'methods' => 'POST',
        'callback' => 'pupilovo_save_customer_address',
        'permission_callback' => 'is_user_logged_in',
    ]);
});

function pupilovo_address_fields(string $type): array
{
    $fields = ['first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'postcode', 'country', 'state', 'phone'];
    // WC_Customer has shipping_phone, but no native shipping_email property.
    if ($type === 'billing') $fields[] = 'email';
    return $fields;
}

function pupilovo_customer_addresses_response(WC_Customer $customer): WP_REST_Response
{
    $data = [];
    foreach (['billing', 'shipping'] as $type) {
        foreach (pupilovo_address_fields($type) as $field) {
            $data[$type][$field] = $customer->{'get_' . $type . '_' . $field}('edit');
        }
    }
    $response = new WP_REST_Response($data);
    $response->header('Cache-Control', 'private, no-store, max-age=0');
    return $response;
}

function pupilovo_get_customer_addresses()
{
    if (!class_exists('WC_Customer')) {
        return new WP_Error('woocommerce_unavailable', 'Adresy są chwilowo niedostępne.', ['status' => 503]);
    }
    return pupilovo_customer_addresses_response(new WC_Customer(get_current_user_id()));
}

function pupilovo_save_customer_address(WP_REST_Request $request)
{
    if (!class_exists('WC_Customer')) {
        return new WP_Error('woocommerce_unavailable', 'Adresy są chwilowo niedostępne.', ['status' => 503]);
    }
    $type = $request->get_url_params()['type'];
    $input = $request->get_json_params();
    $fields = pupilovo_address_fields($type);
    $invalid = fn($message) => new WP_Error('invalid_address', $message, ['status' => 400]);
    if (!is_array($input) || !$input || array_diff(array_keys($input), $fields)
        || $request->get_query_params()) {
        return $invalid('Prześlij wyłącznie pola adresu.');
    }
    $customer = new WC_Customer(get_current_user_id());
    $address = [];
    foreach ($fields as $field) {
        $value = $input[$field] ?? $customer->{'get_' . $type . '_' . $field}('edit');
        if ((array_key_exists($field, $input) && !is_string($input[$field])) || !is_string($value) || strlen($value) > 500) {
            return $invalid('Nieprawidłowa wartość pola adresu: ' . $field);
        }
        $address[$field] = sanitize_text_field($value);
    }
    $address['country'] = strtoupper($address['country']);
    if (!isset(WC()->countries->get_countries()[$address['country']])) {
        return $invalid('Podaj prawidłowy dwuliterowy kod kraju.');
    }
    $definitions = WC()->countries->get_address_fields($address['country'], $type . '_');
    foreach ($fields as $field) {
        if (!empty($definitions[$type . '_' . $field]['required']) && $address[$field] === '') {
            return $invalid('Uzupełnij wymagane pole: ' . wp_strip_all_tags($definitions[$type . '_' . $field]['label']));
        }
    }
    if ($type === 'billing') {
        if (!is_email($address['email'])) return $invalid('Podaj prawidłowy adres e-mail.');
        $address['email'] = sanitize_email($address['email']);
    }
    if ($address['phone'] !== '' && !WC_Validation::is_phone($address['phone'])) {
        return $invalid('Podaj prawidłowy numer telefonu.');
    }
    if ($address['postcode'] !== '' && !WC_Validation::is_postcode($address['postcode'], $address['country'])) {
        return $invalid('Podaj prawidłowy kod pocztowy.');
    }
    $address['postcode'] = wc_format_postcode($address['postcode'], $address['country']);
    $states = WC()->countries->get_states($address['country']);
    if (is_array($states) && $states && $address['state'] !== '' && !isset($states[$address['state']])) {
        return $invalid('Podaj prawidłowy kod regionu dla wybranego kraju.');
    }
    try {
        foreach ($address as $field => $value) {
            $customer->{'set_' . $type . '_' . $field}($value);
        }
        $customer->save();
    } catch (WC_Data_Exception $exception) {
        return $invalid($exception->getMessage());
    }
    return pupilovo_customer_addresses_response($customer);
}
