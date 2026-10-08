<?php

namespace Pupilovo\SupplierHub\Domain\Mapping;

defined('ABSPATH') || exit;

final class FieldPath {
    public function get(array $record, string $path) {
        $path = trim(str_replace(['/', '[', ']'], ['.', '.', ''], $path), '. ');
        if ($path === '') {
            return null;
        }
        $segments = array_values(array_filter(explode('.', $path), static fn(string $value): bool => $value !== ''));

        return $this->walk($record, $segments);
    }

    /** @return array<int, string> */
    public function flatten_keys(array $record, string $prefix = ''): array {
        $keys = [];
        foreach ($record as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value) && $value !== []) {
                if (array_is_list($value)) {
                    $first = $value[0] ?? null;
                    $keys = array_merge($keys, is_array($first) ? $this->flatten_keys($first, $path) : [$path]);
                } else {
                    $keys = array_merge($keys, $this->flatten_keys($value, $path));
                }
            } else {
                $keys[] = $path;
            }
        }

        return array_values(array_unique($keys));
    }

    private function walk($value, array $segments) {
        if ($segments === []) {
            return $value;
        }
        $segment = array_shift($segments);
        if (!is_array($value)) {
            return null;
        }
        if (array_is_list($value) && !ctype_digit($segment)) {
            $values = [];
            foreach ($value as $item) {
                $resolved = $this->walk($item, [$segment, ...$segments]);
                if ($resolved !== null) {
                    $values[] = $resolved;
                }
            }

            return $values;
        }
        if (!array_key_exists($segment, $value)) {
            return null;
        }

        return $this->walk($value[$segment], $segments);
    }
}
