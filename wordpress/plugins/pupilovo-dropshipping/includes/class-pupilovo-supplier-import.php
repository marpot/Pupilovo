<?php
defined('ABSPATH') || exit;

final class Pupilovo_Supplier_Import {
    private const MAX_BYTES = 104857600; // 100 MiB; preview only
    private const MAX_ROWS = 20;

    public static function preview_upload(array $upload, array $profile) {
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return new WP_Error('upload', 'Błąd przesyłania pliku.');
        }
        $path = $upload['tmp_name'] ?? '';
        if (!is_uploaded_file($path) || !is_readable($path)) {
            return new WP_Error('upload', 'Nieprawidłowy plik.');
        }
        $size = filesize($path);
        if ($size === false || $size < 1 || $size > self::MAX_BYTES) {
            return new WP_Error('size', 'Plik pusty lub większy niż 100 MiB.');
        }
        $format = $profile['format'] ?? '';
        $extension = strtolower(pathinfo((string) ($upload['name'] ?? ''), PATHINFO_EXTENSION));
        if ($extension !== $format) {
            return new WP_Error('format', 'Rozszerzenie pliku nie odpowiada profilowi.');
        }
        try {
            return $format === 'xml' ? self::xml($path, $profile)
                : ($format === 'csv' ? self::csv($path, $profile)
                : new WP_Error('format', 'Nieobsługiwany format.'));
        } catch (Throwable $e) {
            return new WP_Error('parse', 'Nie udało się odczytać pliku.');
        }
    }

    private static function mapped(array $source, array $mapping): array {
        $result = [];
        foreach (Pupilovo_Dropshipping_Product_Map::keys() as $field) {
            $key = $mapping[$field] ?? '';
            $result[$field] = $key !== '' ? (string) ($source[$key] ?? '') : '';
        }
        return $result;
    }

    private static function csv(string $path, array $profile) {
        $handle = fopen($path, 'rb');
        if (!$handle) return new WP_Error('csv', 'Nie można otworzyć CSV.');
        $delimiter = $profile['delimiter'] ?? ';';
        if (strlen($delimiter) !== 1) $delimiter = ';';
        try {
            $headers = fgetcsv($handle, 0, $delimiter, '"', '');
            if (!$headers || count(array_filter($headers, 'strlen')) === 0) {
                return new WP_Error('csv', 'Brak nagłówków CSV.');
            }
            $headers = array_map(static fn($v) => trim((string) $v), $headers);
            if (count($headers) !== count(array_unique($headers))) {
                return new WP_Error('csv', 'Powtarzające się nagłówki CSV.');
            }
            $rows = [];
            while (count($rows) < self::MAX_ROWS && ($values = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                if (count($values) !== count($headers)) continue;
                $rows[] = self::mapped(array_combine($headers, $values), $profile['mapping'] ?? []);
            }
            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private static function xml(string $path, array $profile) {
        if (!class_exists('XMLReader')) return new WP_Error('xml', 'Brak rozszerzenia XMLReader w PHP.');
        $record = $profile['record_element'] ?? 'product';
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_.-]*$/', $record)) {
            return new WP_Error('xml', 'Nieprawidłowa nazwa elementu XML.');
        }
        $reader = new XMLReader();
        if (!$reader->open($path, null, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            return new WP_Error('xml', 'Nie można otworzyć XML.');
        }
        $reader->setParserProperty(XMLReader::SUBST_ENTITIES, false);
        $reader->setParserProperty(XMLReader::LOADDTD, false);
        $rows = [];
        try {
            while ($reader->read() && count($rows) < self::MAX_ROWS) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== $record) continue;
                $depth = $reader->depth;
                $source = [];
                if ($reader->isEmptyElement) {
                    $rows[] = self::mapped($source, $profile['mapping'] ?? []);
                    continue;
                }
                while ($reader->read()) {
                    if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->depth === $depth) break;
                    if ($reader->nodeType === XMLReader::ELEMENT && $reader->depth === $depth + 1) {
                        $name = $reader->localName;
                        $source[$name] = $reader->readString();
                    }
                }
                $rows[] = self::mapped($source, $profile['mapping'] ?? []);
            }
            return $rows;
        } finally {
            $reader->close();
        }
    }
}
