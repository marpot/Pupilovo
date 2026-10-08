<?php

namespace Pupilovo\SupplierHub\Infrastructure\Feed;

use Pupilovo\SupplierHub\Domain\Feed\FeedParserInterface;
use Pupilovo\SupplierHub\Domain\Mapping\FieldPath;

defined('ABSPATH') || exit;

abstract class AbstractFeedParser implements FeedParserInterface {
    public function inspect(string $path, int $sample_size = 5): array {
        $samples = [];
        $fields = [];
        $paths = new FieldPath();
        foreach ($this->records($path, max(1, min(20, $sample_size))) as $record) {
            $samples[] = $record;
            $fields = array_merge($fields, $paths->flatten_keys($record));
        }

        return ['fields' => array_values(array_unique($fields)), 'samples' => $samples];
    }
}
