<?php

namespace Pupilovo\SupplierHub\Infrastructure\Repository;

use Pupilovo\SupplierHub\Infrastructure\Database\Schema;
use Pupilovo\SupplierHub\Infrastructure\Security\SecretCipher;

defined('ABSPATH') || exit;

final class SecretRepository {
    public function __construct(private readonly SecretCipher $cipher = new SecretCipher()) {}

    public function put(int $supplier_id, string $name, array $secret): int {
        global $wpdb;

        $table = Schema::table('secrets');
        $name = sanitize_key($name);
        if ($supplier_id < 1 || $name === '') {
            throw new \InvalidArgumentException('Nieprawidłowy identyfikator sekretu.');
        }
        $ciphertext = $this->cipher->encrypt($secret);
        $now = current_time('mysql', true);
        $existing = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$table} WHERE supplier_id = %d AND secret_name = %s", $supplier_id, $name)
        );
        if ($existing > 0) {
            $result = $wpdb->update(
                $table,
                ['ciphertext' => $ciphertext, 'key_id' => $this->cipher->current_key_id(), 'updated_at' => $now],
                ['id' => $existing],
                ['%s', '%s', '%s'],
                ['%d']
            );
            if ($result === false) {
                throw new \RuntimeException('Nie udało się zaktualizować danych dostępowych.');
            }

            return $existing;
        }

        $result = $wpdb->insert(
            $table,
            ['supplier_id' => $supplier_id, 'secret_name' => $name, 'ciphertext' => $ciphertext, 'key_id' => $this->cipher->current_key_id(), 'created_at' => $now, 'updated_at' => $now],
            ['%d', '%s', '%s', '%s', '%s', '%s']
        );
        if ($result !== 1) {
            throw new \RuntimeException('Nie udało się zapisać danych dostępowych.');
        }

        return (int) $wpdb->insert_id;
    }

    public function get(int $supplier_id, string $name): ?array {
        global $wpdb;

        $table = Schema::table('secrets');
        $ciphertext = $wpdb->get_var(
            $wpdb->prepare("SELECT ciphertext FROM {$table} WHERE supplier_id = %d AND secret_name = %s", $supplier_id, sanitize_key($name))
        );

        return is_string($ciphertext) ? $this->cipher->decrypt($ciphertext) : null;
    }

    public function has(int $supplier_id, string $name): bool {
        global $wpdb;

        $table = Schema::table('secrets');

        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE supplier_id = %d AND secret_name = %s", $supplier_id, sanitize_key($name))
        ) > 0;
    }

    public function delete_for_supplier(int $supplier_id): void {
        global $wpdb;
        $wpdb->delete(Schema::table('secrets'), ['supplier_id' => $supplier_id], ['%d']);
    }
}
