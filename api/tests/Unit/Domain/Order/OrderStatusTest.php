<?php

namespace Tests\Unit\Domain\Order;

use App\Domain\Order\Enums\OrderStatus;
use PHPUnit\Framework\TestCase;

class OrderStatusTest extends TestCase
{
    public function test_all_statuses_exist(): void
    {
        $this->assertCount(6, OrderStatus::all());
        $this->assertContains(OrderStatus::PendingReview->value, OrderStatus::all());
        $this->assertContains(OrderStatus::Cancelled->value, OrderStatus::all());
    }

    public function test_can_be_cancelled(): void
    {
        $this->assertTrue(OrderStatus::PendingReview->canBeCancelled());
        $this->assertTrue(OrderStatus::Confirmed->canBeCancelled());
        $this->assertFalse(OrderStatus::Cancelled->canBeCancelled());
        $this->assertFalse(OrderStatus::Shipped->canBeCancelled());
        $this->assertFalse(OrderStatus::Delivered->canBeCancelled());
    }

    public function test_labels(): void
    {
        $this->assertSame('Pending Review', OrderStatus::PendingReview->label());
        $this->assertSame('Cancelled', OrderStatus::Cancelled->label());
    }
}
