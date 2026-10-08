<?php

namespace Pupilovo\SupplierHub\Domain\Contract;

defined('ABSPATH') || exit;

interface APIAdapterInterface {
    public function key(): string;

    /**
     * @return array{ok: bool, message: string, metadata?: array<string, mixed>}
     */
    public function test_connection(array $configuration): array;

    /** @return iterable<array<string, mixed>> */
    public function products(array $configuration, ?string $cursor = null): iterable;
}
