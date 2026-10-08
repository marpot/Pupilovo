<?php

namespace Pupilovo\SupplierHub\Domain\Fulfillment;

defined('ABSPATH') || exit;

final class FulfillmentStatus {
    public const PENDING = 'pending';
    public const READY = 'ready';
    public const MANUALLY_APPROVED = 'manually_approved';
    public const SENT = 'sent';
    public const ACKNOWLEDGED = 'acknowledged';
    public const SHIPPED = 'shipped';
    public const DELIVERED = 'delivered';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';

    public static function all(): array {
        return [
            self::PENDING, self::READY, self::MANUALLY_APPROVED,
            self::SENT, self::ACKNOWLEDGED, self::SHIPPED,
            self::DELIVERED, self::FAILED, self::CANCELLED,
        ];
    }

    public static function is_valid(string $status): bool {
        return in_array($status, self::all(), true);
    }
}
