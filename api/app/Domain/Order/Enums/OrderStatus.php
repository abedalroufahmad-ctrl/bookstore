<?php

namespace App\Domain\Order\Enums;

enum OrderStatus: string
{
    case PendingReview = 'pending_review';
    case Confirmed = 'confirmed';
    case Preparing = 'preparing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingReview => 'Pending Review',
            self::Confirmed => 'Confirmed',
            self::Preparing => 'Preparing',
            self::Shipped => 'Shipped',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Whether this status allows cancellation (stock restoration).
     */
    public function canBeCancelled(): bool
    {
        return $this !== self::Cancelled
            && $this !== self::Shipped
            && $this !== self::Delivered;
    }

    /**
     * Valid statuses for workflow transitions.
     */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }
}
