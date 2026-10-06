<?php

namespace App\Domain\Order\Enums;

enum OrderStatus: string
{
    case PendingWarehouseReview = 'pending_warehouse_review';
    case AwaitingCustomerConfirmation = 'awaiting_customer_confirmation';
    case ResubmittedToWarehouse = 'resubmitted_to_warehouse';
    case ProcessingFulfillment = 'processing_fulfillment';
    case ShippedCollectingPayment = 'shipped_collecting_payment';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    public function label(): string
    {
        return match ($this) {
            self::PendingWarehouseReview => 'Sent to warehouse (pending review)',
            self::AwaitingCustomerConfirmation => 'Awaiting customer confirmation',
            self::ResubmittedToWarehouse => 'Resubmitted to warehouse',
            self::ProcessingFulfillment => 'Processing fulfillment',
            self::ShippedCollectingPayment => 'Shipped / collecting payment',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Whether this status allows cancellation (early workflow only).
     */
    public function canBeCancelled(): bool
    {
        return match ($this) {
            self::PendingWarehouseReview,
            self::AwaitingCustomerConfirmation,
            self::ResubmittedToWarehouse,
            self::ProcessingFulfillment => true,
            default => false,
        };
    }

    /** @return list<string> */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Map legacy statuses from older installs (optional migrations).
     */
    public static function normalizeStored(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        $try = self::tryFrom($value);
        if ($try !== null) {
            return $try;
        }

        return match ($value) {
            'pending_review' => self::PendingWarehouseReview,
            'confirmed',
            'preparing' => self::ProcessingFulfillment,
            'shipped' => self::ShippedCollectingPayment,
            'delivered' => self::Completed,
            default => null,
        };
    }
}
