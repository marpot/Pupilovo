<?php

namespace Pupilovo\SupplierHub\Infrastructure\Security;

use WP_Error;

defined('ABSPATH') || exit;

final class SourceUrlGuard {
    /** @var callable(string): array<int, string> */
    private $resolver;

    public function __construct(?callable $resolver = null) {
        $this->resolver = $resolver ?? [$this, 'resolve'];
    }

    public function validate(string $url, bool $allow_http = false) {
        $url = trim($url);
        $parts = wp_parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return new WP_Error('invalid_source_url', 'Podaj pełny adres źródła.');
        }
        $scheme = strtolower((string) $parts['scheme']);
        if ($scheme !== 'https' && !($allow_http && $scheme === 'http')) {
            return new WP_Error('unsafe_source_scheme', 'Źródło musi używać HTTPS, chyba że jawnie zezwolono na HTTP.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return new WP_Error('url_credentials_forbidden', 'Danych dostępowych nie wolno umieszczać w adresie URL.');
        }
        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
        if (!in_array($port, [80, 443], true)) {
            return new WP_Error('unsafe_source_port', 'Dozwolone są wyłącznie porty HTTP 80 i HTTPS 443.');
        }

        $host = strtolower(rtrim((string) $parts['host'], '.'));
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return new WP_Error('private_source_host', 'Lokalne adresy źródeł są zablokowane.');
        }
        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : ($this->resolver)($host);
        if ($addresses === []) {
            return new WP_Error('source_dns_failed', 'Nie można bezpiecznie rozwiązać adresu hosta.');
        }
        foreach ($addresses as $address) {
            if (!$this->is_public_ip($address)) {
                return new WP_Error('private_source_ip', 'Źródło wskazuje na prywatny lub zarezerwowany adres IP.');
            }
        }

        return esc_url_raw($url, ['http', 'https']);
    }

    public function resolve(string $host): array {
        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        if (!is_array($records)) {
            return [];
        }
        $addresses = [];
        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        return array_values(array_unique($addresses));
    }

    private function is_public_ip(string $ip): bool {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}
