<?php

namespace Pupilovo\SupplierHub\Domain\Feed;

defined('ABSPATH') || exit;

interface FeedParserInterface {
    /** @return iterable<array<string, mixed>> */
    public function records(string $path, ?int $limit = null): iterable;

    /** @return array{fields:array<int,string>,samples:array<int,array<string,mixed>>} */
    public function inspect(string $path, int $sample_size = 5): array;
}
