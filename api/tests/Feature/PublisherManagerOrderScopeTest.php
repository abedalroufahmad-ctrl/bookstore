<?php

namespace Tests\Feature;

use App\Domain\Order\Enums\OrderStatus;
use App\Models\Employee;
use App\Models\Order;
use App\Models\Publisher;
use App\Models\Warehouse;

class PublisherManagerOrderScopeTest extends MongoFeatureTestCase
{
    private Employee $publisherManager;

    private Warehouse $ownWarehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $publisher = Publisher::create(['name' => 'Scoped Press']);
        $this->ownWarehouse = Warehouse::create(['name' => 'Own', 'publisher_id' => (string) $publisher->_id]);
        $this->publisherManager = Employee::create([
            'name' => 'PM',
            'email' => 'pm@staff.test',
            'password' => 'secret123',
            'role' => 'publisher_manager',
            'publisher_id' => (string) $publisher->_id,
        ]);
    }

    private function order(?string $warehouseId): Order
    {
        return Order::create([
            'customer_id' => 'someone',
            'warehouse_id' => $warehouseId,
            'items' => [],
            'status' => OrderStatus::PendingWarehouseReview->value,
            'total' => 0,
        ]);
    }

    public function test_publisher_manager_cannot_access_order_without_a_warehouse(): void
    {
        $orphan = $this->order(null);
        $headers = $this->employeeHeaders($this->publisherManager);

        $this->getJson("/api/v1/admin/orders/{$orphan->_id}", $headers)->assertForbidden();
        $this->deleteJson("/api/v1/admin/orders/{$orphan->_id}", [], $headers)->assertForbidden();
        $this->assertNotNull(Order::find((string) $orphan->_id));
    }

    public function test_publisher_manager_cannot_access_other_publishers_order(): void
    {
        $foreign = Warehouse::create(['name' => 'Foreign', 'publisher_id' => 'another-publisher']);
        $order = $this->order((string) $foreign->_id);

        $this->getJson("/api/v1/admin/orders/{$order->_id}", $this->employeeHeaders($this->publisherManager))
            ->assertForbidden();
    }

    public function test_publisher_manager_can_access_own_warehouse_order(): void
    {
        $order = $this->order((string) $this->ownWarehouse->_id);

        $this->getJson("/api/v1/admin/orders/{$order->_id}", $this->employeeHeaders($this->publisherManager))
            ->assertOk();
    }
}
