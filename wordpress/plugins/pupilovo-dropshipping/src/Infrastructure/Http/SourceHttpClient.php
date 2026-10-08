<?php

namespace Pupilovo\SupplierHub\Infrastructure\Http;

use Pupilovo\SupplierHub\Infrastructure\Security\HeaderPolicy;
use Pupilovo\SupplierHub\Infrastructure\Security\SourceUrlGuard;
use WP_Error;

defined('ABSPATH') || exit;

final class SourceHttpClient {
    public const DEFAULT_MAX_BYTES = 104857600;
    private const MAX_REDIRECTS = 3;

    public function __construct(
        private readonly SourceUrlGuard $guard = new SourceUrlGuard(),
        private readonly HeaderPolicy $header_policy = new HeaderPolicy()
    ) {}

    /**
     * @return array{path:string,url:string,bytes:int,checksum:string,contentType:string}|WP_Error
     */
    public function download(
        string $url,
        array $headers = [],
        bool $allow_http = false,
        int $max_bytes = self::DEFAULT_MAX_BYTES,
        int $timeout = 20
    ) {
        $max_bytes = min(self::DEFAULT_MAX_BYTES, max(1024, $max_bytes));
        $timeout = min(30, max(3, $timeout));
        try {
            $headers = $this->header_policy->sanitize($headers);
        } catch (\InvalidArgumentException $error) {
            return new WP_Error('invalid_source_headers', $error->getMessage());
        }

        $current = $url;
        for ($redirect = 0; $redirect <= self::MAX_REDIRECTS; ++$redirect) {
            $validated = $this->guard->validate($current, $allow_http);
            if (is_wp_error($validated)) {
                return $validated;
            }

            $temporary = wp_tempnam('pupilovo-supplier-feed');
            if (!is_string($temporary) || $temporary === '') {
                return new WP_Error('temporary_file_failed', 'Nie można utworzyć bezpiecznego pliku tymczasowego.');
            }

            $response = wp_safe_remote_get($validated, [
                'timeout' => $timeout,
                'redirection' => 0,
                'reject_unsafe_urls' => true,
                'limit_response_size' => $max_bytes + 1,
                'stream' => true,
                'filename' => $temporary,
                'headers' => $headers,
                'user-agent' => 'Pupilovo-Supplier-Hub/' . PUPILOVO_SUPPLIER_HUB_VERSION,
            ]);
            if (is_wp_error($response)) {
                wp_delete_file($temporary);

                return new WP_Error('source_download_failed', 'Nie udało się pobrać źródła.', ['cause' => $response->get_error_code()]);
            }

            $status = (int) wp_remote_retrieve_response_code($response);
            if ($status >= 300 && $status < 400) {
                $location = wp_remote_retrieve_header($response, 'location');
                wp_delete_file($temporary);
                if (!is_string($location) || $location === '' || $redirect === self::MAX_REDIRECTS) {
                    return new WP_Error('unsafe_redirect', 'Źródło zwróciło nieprawidłowe lub zbyt liczne przekierowania.');
                }
                $current = \WP_Http::make_absolute_url($location, $validated);
                continue;
            }
            if ($status < 200 || $status >= 300) {
                wp_delete_file($temporary);

                return new WP_Error('source_http_error', 'Źródło zwróciło kod HTTP ' . $status . '.', ['statusCode' => $status]);
            }

            $bytes = filesize($temporary);
            if ($bytes === false || $bytes < 1 || $bytes > $max_bytes) {
                wp_delete_file($temporary);

                return new WP_Error('source_size_invalid', 'Źródło jest puste albo przekracza limit rozmiaru.');
            }
            $checksum = hash_file('sha256', $temporary);
            if (!is_string($checksum)) {
                wp_delete_file($temporary);

                return new WP_Error('source_checksum_failed', 'Nie można obliczyć sumy kontrolnej źródła.');
            }

            return [
                'path' => $temporary,
                'url' => $validated,
                'bytes' => $bytes,
                'checksum' => $checksum,
                'contentType' => sanitize_text_field((string) wp_remote_retrieve_header($response, 'content-type')),
            ];
        }

        return new WP_Error('source_download_failed', 'Nie udało się pobrać źródła.');
    }
}
