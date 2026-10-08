<?php

namespace Pupilovo\SupplierHub\Infrastructure\Feed;

defined('ABSPATH') || exit;

final class JsonFeedParser extends AbstractFeedParser {
    private const MAX_RECORD_BYTES = 5242880;

    public function __construct(private readonly string $file) {
        if (!is_readable($file)) {
            throw new \InvalidArgumentException('Plik JSON jest nieczytelny.');
        }
    }

    public function records(string $path, ?int $limit = null): iterable {
        $handle = fopen($this->file, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Nie można otworzyć JSON.');
        }
        try {
            $this->seek_records_array($handle, $path);
            $count = 0;
            foreach ($this->array_values($handle) as $value) {
                if (!is_array($value)) {
                    throw new \RuntimeException('Każdy rekord produktu JSON musi być obiektem.');
                }
                yield $value;
                ++$count;
                if ($limit !== null && $count >= $limit) {
                    break;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /** @param resource $handle */
    private function seek_records_array($handle, string $path): void {
        $target = trim($path);
        if ($target === '' || $target === '$') {
            while (($char = fgetc($handle)) !== false) {
                if (!ctype_space($char)) {
                    if ($char !== '[') {
                        throw new \RuntimeException('Główny element JSON nie jest tablicą.');
                    }

                    return;
                }
            }
            throw new \RuntimeException('Pusty plik JSON.');
        }

        $segments = array_values(array_filter(explode('.', trim($target, '$.'))));
        $target_key = end($segments);
        while (($char = fgetc($handle)) !== false) {
            if ($char !== '"') {
                continue;
            }
            $key = $this->read_string($handle);
            $next = $this->next_non_whitespace($handle);
            if ($next !== ':') {
                continue;
            }
            $value_start = $this->next_non_whitespace($handle);
            if ($key === $target_key && $value_start === '[') {
                return;
            }
            if ($value_start === false) {
                break;
            }
            if ($value_start === '"') {
                $this->read_string($handle);
            }
        }

        throw new \RuntimeException('Nie znaleziono tablicy rekordów pod wskazaną ścieżką JSON.');
    }

    /** @param resource $handle @return iterable<mixed> */
    private function array_values($handle): iterable {
        while (true) {
            $first = $this->next_non_whitespace($handle);
            while ($first === ',') {
                $first = $this->next_non_whitespace($handle);
            }
            if ($first === false || $first === ']') {
                return;
            }

            $json = $first;
            $in_string = $first === '"';
            $escaped = false;
            $depth = in_array($first, ['{', '['], true) ? 1 : 0;
            while (true) {
                $char = fgetc($handle);
                if ($char === false) {
                    throw new \RuntimeException('Nieoczekiwany koniec rekordu JSON.');
                }
                if (!$in_string && $depth === 0 && ($char === ',' || $char === ']')) {
                    if ($char === ']') {
                        fseek($handle, -1, SEEK_CUR);
                    }
                    break;
                }
                $json .= $char;
                if (strlen($json) > self::MAX_RECORD_BYTES) {
                    throw new \RuntimeException('Pojedynczy rekord JSON przekracza limit 5 MiB.');
                }
                if ($in_string) {
                    if ($escaped) {
                        $escaped = false;
                    } elseif ($char === '\\') {
                        $escaped = true;
                    } elseif ($char === '"') {
                        $in_string = false;
                        if ($depth === 0) {
                            break;
                        }
                    }
                } else {
                    if ($char === '"') {
                        $in_string = true;
                    } elseif ($char === '{' || $char === '[') {
                        ++$depth;
                    } elseif ($char === '}' || $char === ']') {
                        --$depth;
                        if ($depth === 0) {
                            break;
                        }
                    }
                }
            }

            $decoded = json_decode(trim($json), true, 128, JSON_THROW_ON_ERROR);
            yield $decoded;
        }
    }

    /** @param resource $handle */
    private function read_string($handle): string {
        $json = '"';
        $escaped = false;
        while (($char = fgetc($handle)) !== false) {
            $json .= $char;
            if ($escaped) {
                $escaped = false;
            } elseif ($char === '\\') {
                $escaped = true;
            } elseif ($char === '"') {
                return (string) json_decode($json, true, 2, JSON_THROW_ON_ERROR);
            }
        }

        throw new \RuntimeException('Nieprawidłowy ciąg JSON.');
    }

    /** @param resource $handle */
    private function next_non_whitespace($handle) {
        while (($char = fgetc($handle)) !== false) {
            if (!ctype_space($char)) {
                return $char;
            }
        }

        return false;
    }
}
