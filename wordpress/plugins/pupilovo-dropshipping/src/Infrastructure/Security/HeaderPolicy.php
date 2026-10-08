<?php

namespace Pupilovo\SupplierHub\Infrastructure\Security;

defined('ABSPATH') || exit;

final class HeaderPolicy {
    private const DENIED = [
        'host', 'cookie', 'set-cookie', 'content-length', 'transfer-encoding',
        'connection', 'proxy-authorization', 'proxy-authenticate', 'forwarded',
        'x-forwarded-for', 'x-forwarded-host', 'x-real-ip',
    ];

    public function sanitize(array $headers): array {
        $result = [];
        foreach ($headers as $name => $value) {
            $normalized = strtolower(trim((string) $name));
            if (!preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $normalized) || in_array($normalized, self::DENIED, true)) {
                throw new \InvalidArgumentException('Niedozwolony nagłówek HTTP: ' . $normalized);
            }
            if (!is_scalar($value) || preg_match('/[\r\n]/', (string) $value)) {
                throw new \InvalidArgumentException('Nieprawidłowa wartość nagłówka HTTP.');
            }
            $result[$normalized] = trim((string) $value);
        }

        return $result;
    }
}
