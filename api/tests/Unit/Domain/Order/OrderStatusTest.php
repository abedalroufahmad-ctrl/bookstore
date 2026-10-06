<?php

namespace Tests\Unit\Domain\Order;

use App\Domain\Order\Enums\OrderStatus;
use PHPUnit\Framework\TestCase;

class OrderStatusTest extends TestCase
{
    public function test_all_statuses_exist(): void
    {
        $all = OrderStatus::all();
        $this->assertCount(7, $all);
        $this->assertContains(OrderStatus::PendingWarehouseReview->value, $all);
        $this->assertContains(OrderStatus::AwaitingCustomerConfirmation->value, $all);
        $this->assertContains(OrderStatus::ResubmittedToWarehouse->value, $all);
        $this->assertContains(OrderStatus::ProcessingFulfillment->value, $all);
        $this->assertContains(OrderStatus::ShippedCollectingPayment->value, $all);
        $this->assertContains(OrderStatus::Completed->value, $all);
        $this->assertContains(OrderStatus::Cancelled->value, $all);
    }

    public function test_normalize_legacy_maps(): void
    {
        $this->assertSame(
            OrderStatus::PendingWarehouseReview,
            OrderStatus::normalizeStored('pending_review')
        );
        $this->assertSame(
            OrderStatus::Completed,
            OrderStatus::normalizeStored('delivered')
        );
    }

    public function test_can_be_cancelled(): void
    {
        $this->assertTrue(OrderStatus::PendingWarehouseReview->canBeCancelled());
        $this->assertTrue(OrderStatus::AwaitingCustomerConfirmation->canBeCancelled());
        $this->assertTrue(OrderStatus::ResubmittedToWarehouse->canBeCancelled());
        $this->assertTrue(OrderStatus::ProcessingFulfillment->canBeCancelled());
        $this->assertFalse(OrderStatus::Cancelled->canBeCancelled());
        $this->assertFalse(OrderStatus::ShippedCollectingPayment->canBeCancelled());
        $this->assertFalse(OrderStatus::Completed->canBeCancelled());
    }

    public function test_labels(): void
    {
        $this->assertStringContainsString('warehouse', OrderStatus::PendingWarehouseReview->label());
        $this->assertSame('Cancelled', OrderStatus::Cancelled->label());
    }
}
