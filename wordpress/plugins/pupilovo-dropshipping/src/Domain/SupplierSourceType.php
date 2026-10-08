<?php

namespace Pupilovo\SupplierHub\Domain;

defined('ABSPATH') || exit;

final class SupplierSourceType {
    public const FILE_XML = 'file_xml';
    public const URL_XML = 'url_xml';
    public const FILE_CSV = 'file_csv';
    public const URL_CSV = 'url_csv';
    public const FILE_TSV = 'file_tsv';
    public const URL_TSV = 'url_tsv';
    public const FILE_JSON = 'file_json';
    public const URL_JSON = 'url_json';
    public const API_ADAPTER = 'api_adapter';

    public static function all(): array {
        return [
            self::FILE_XML,
            self::URL_XML,
            self::FILE_CSV,
            self::URL_CSV,
            self::FILE_TSV,
            self::URL_TSV,
            self::FILE_JSON,
            self::URL_JSON,
            self::API_ADAPTER,
        ];
    }

    public static function is_valid(string $value): bool {
        return in_array($value, self::all(), true);
    }
}
