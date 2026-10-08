<?php

namespace Pupilovo\SupplierHub\Infrastructure\Feed;

defined('ABSPATH') || exit;

final class DelimitedFeedParser extends AbstractFeedParser {
    public function __construct(
        private readonly string $file,
        private readonly string $delimiter = ','
    ) {
        if (!is_readable($file) || strlen($delimiter) !== 1) {
            throw new \InvalidArgumentException('Nieprawidłowy plik lub separator danych kolumnowych.');
        }
    }

    public function records(string $path, ?int $limit = null): iterable {
        $handle = fopen($this->file, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Nie można otworzyć pliku CSV/TSV.');
        }
        try {
            $headers = fgetcsv($handle, 0, $this->delimiter, '"', '');
            if (!is_array($headers) || $headers === []) {
                throw new \RuntimeException('Plik nie zawiera nagłówków.');
            }
            $headers = array_map(static function ($value): string {
                $value = trim((string) $value);

                return str_starts_with($value, "\xEF\xBB\xBF") ? substr($value, 3) : $value;
            }, $headers);
            if (in_array('', $headers, true) || count($headers) !== count(array_unique($headers))) {
                throw new \RuntimeException('Nagłówki muszą być niepuste i unikatowe.');
            }

            $count = 0;
            while (($values = fgetcsv($handle, 0, $this->delimiter, '"', '')) !== false) {
                if ($values === [null] || count($values) !== count($headers)) {
                    continue;
                }
                yield array_combine($headers, $values);
                ++$count;
                if ($limit !== null && $count >= $limit) {
                    break;
                }
            }
        } finally {
            fclose($handle);
        }
    }
}
