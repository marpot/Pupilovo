<?php

namespace Pupilovo\SupplierHub\Infrastructure\Security;

defined('ABSPATH') || exit;

final class SecretCipher {
    private const CONTEXT = 'pupilovo-supplier-hub-secrets-v1';

    public function encrypt(array $value): string {
        $plaintext = wp_json_encode($value, JSON_UNESCAPED_SLASHES);
        if (!is_string($plaintext)) {
            throw new \RuntimeException('Nie udało się zakodować danych dostępowych.');
        }

        $key = $this->key();
        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);
            $payload = ['v' => 1, 'alg' => 'secretbox', 'kid' => $this->key_id($key), 'n' => base64_encode($nonce), 'c' => base64_encode($ciphertext)];
        } elseif (function_exists('openssl_encrypt')) {
            $nonce = random_bytes(12);
            $tag = '';
            $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, self::CONTEXT);
            if (!is_string($ciphertext)) {
                throw new \RuntimeException('Szyfrowanie danych dostępowych nie powiodło się.');
            }
            $payload = ['v' => 1, 'alg' => 'aes-256-gcm', 'kid' => $this->key_id($key), 'n' => base64_encode($nonce), 't' => base64_encode($tag), 'c' => base64_encode($ciphertext)];
        } else {
            throw new \RuntimeException('Brak obsługi bezpiecznego szyfrowania w PHP.');
        }

        return base64_encode(wp_json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    public function decrypt(string $encoded): array {
        $json = base64_decode($encoded, true);
        $payload = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($payload) || ($payload['v'] ?? null) !== 1) {
            throw new \RuntimeException('Nieprawidłowy format zaszyfrowanych danych.');
        }

        $key = $this->key();
        if (!hash_equals($this->key_id($key), (string) ($payload['kid'] ?? ''))) {
            throw new \RuntimeException('Klucz szyfrowania uległ zmianie. Dane wymagają ponownej konfiguracji.');
        }

        $nonce = base64_decode((string) ($payload['n'] ?? ''), true);
        $ciphertext = base64_decode((string) ($payload['c'] ?? ''), true);
        if (!is_string($nonce) || !is_string($ciphertext)) {
            throw new \RuntimeException('Uszkodzone zaszyfrowane dane.');
        }

        if (($payload['alg'] ?? '') === 'secretbox' && function_exists('sodium_crypto_secretbox_open')) {
            $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
        } elseif (($payload['alg'] ?? '') === 'aes-256-gcm' && function_exists('openssl_decrypt')) {
            $tag = base64_decode((string) ($payload['t'] ?? ''), true);
            $plaintext = is_string($tag)
                ? openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, self::CONTEXT)
                : false;
        } else {
            $plaintext = false;
        }

        $decoded = is_string($plaintext) ? json_decode($plaintext, true) : null;
        if (!is_array($decoded)) {
            throw new \RuntimeException('Nie można odszyfrować danych dostępowych.');
        }

        return $decoded;
    }

    public function current_key_id(): string {
        return $this->key_id($this->key());
    }

    private function key(): string {
        $material = defined('PUPILOVO_SUPPLIER_HUB_ENCRYPTION_KEY')
            ? (string) PUPILOVO_SUPPLIER_HUB_ENCRYPTION_KEY
            : wp_salt('auth') . wp_salt('secure_auth');
        if (strlen($material) < 32) {
            throw new \RuntimeException('Klucz szyfrowania musi mieć co najmniej 32 znaki.');
        }

        return hash_hkdf('sha256', $material, 32, self::CONTEXT);
    }

    private function key_id(string $key): string {
        return substr(hash_hmac('sha256', 'key-id', $key), 0, 16);
    }
}
