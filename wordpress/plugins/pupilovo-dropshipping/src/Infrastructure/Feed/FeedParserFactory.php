<?php

namespace Pupilovo\SupplierHub\Infrastructure\Feed;

use Pupilovo\SupplierHub\Domain\Feed\FeedParserInterface;

defined('ABSPATH') || exit;

final class FeedParserFactory {
    public function create(string $format, string $file, array $config = []): FeedParserInterface {
        return match (strtolower($format)) {
            'xml' => new XmlFeedParser($file),
            'csv' => new DelimitedFeedParser($file, $this->delimiter($config['delimiter'] ?? ',')),
            'tsv' => new DelimitedFeedParser($file, "\t"),
            'json' => new JsonFeedParser($file),
            default => throw new \InvalidArgumentException('Nieobsługiwany format feedu: ' . $format),
        };
    }

    private function delimiter($value): string {
        $value = (string) $value;

        return strlen($value) === 1 ? $value : ',';
    }
}
