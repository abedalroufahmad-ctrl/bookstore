<?php

namespace Tests\Unit\Services;

use App\Models\Order;
use App\Models\Publisher;
use App\Models\PublisherCommissionEntry;
use App\Models\Warehouse;
use App\Services\PublisherCommissionAccountService;
use Tests\Feature\MongoFeatureTestCase;

class PublisherCommissionAccountServiceTest extends MongoFeatureTestCase
{
    private PublisherCommissionAccountService $service;

    private Publisher $publisher;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PublisherCommissionAccountService;
        $this->publisher = Publisher::create([
            'name' => 'Commission Press',
            'settings' => ['platform_commission_percent' => 10],
            'commission_owed' => 0,
            'commission_accrued_total' => 0,
            'commission_paid_total' => 0,
        ]);
        $this->warehouse = Warehouse::create([
            'name' => 'WH',
            'publisher_id' => (string) $this->publisher->_id,
        ]);
    }

    public function test_accrue_for_order_increments_owed_once(): void
    {
        $order = Order::create([
            'warehouse_id' => (string) $this->warehouse->_id,
            'platform_commission_amount' => 12.5,
            'status' => 'completed',
        ]);

        $first = $this->service->accrueForOrder($order);
        $second = $this->service->accrueForOrder($order->fresh());

        $this->assertNotNull($first);
        $this->assertSame((string) $first->_id, (string) $second->_id);
        $this->assertSame(1, PublisherCommissionEntry::query()->count());

        $publisher = $this->publisher->fresh();
        $this->assertSame(12.5, (float) $publisher->commission_owed);
        $this->assertSame(12.5, (float) $publisher->commission_accrued_total);
        $this->assertSame(0.0, (float) $publisher->commission_paid_total);
    }

    public function test_settlement_reduces_owed_and_rejects_overpay(): void
    {
        $order = Order::create([
            'warehouse_id' => (string) $this->warehouse->_id,
            'platform_commission_amount' => 20,
            'status' => 'completed',
        ]);
        $this->service->accrueForOrder($order);

        $entry = $this->service->recordSettlement(
            (string) $this->publisher->_id,
            7.5,
            null,
            'Wire',
            'REF-1'
        );

        $this->assertSame(PublisherCommissionEntry::TYPE_SETTLEMENT, $entry->type);
        $publisher = $this->publisher->fresh();
        $this->assertSame(12.5, (float) $publisher->commission_owed);
        $this->assertSame(7.5, (float) $publisher->commission_paid_total);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->recordSettlement((string) $this->publisher->_id, 13);
    }

    public function test_zero_commission_does_not_create_entry(): void
    {
        $order = Order::create([
            'warehouse_id' => (string) $this->warehouse->_id,
            'platform_commission_amount' => 0,
            'status' => 'completed',
        ]);

        $this->assertNull($this->service->accrueForOrder($order));
        $this->assertSame(0, PublisherCommissionEntry::query()->count());
    }
}
