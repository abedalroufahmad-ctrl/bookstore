<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Order;
use App\Models\Publisher;
use App\Models\PublisherCommissionEntry;
use App\Models\Warehouse;
use App\Services\PublisherCommissionAccountService;

class PublisherCommissionAccountTest extends MongoFeatureTestCase
{
    private Publisher $publisher;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->publisher = Publisher::create([
            'name' => 'Ledger Press',
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

    private function employee(string $role, array $attributes = []): Employee
    {
        return Employee::create(array_merge([
            'name' => ucfirst($role),
            'email' => $role.uniqid().'@staff.test',
            'password' => 'secret123',
            'role' => $role,
        ], $attributes));
    }

    public function test_publisher_manager_can_view_account_and_record_settlement(): void
    {
        $pm = $this->employee('publisher_manager', [
            'publisher_id' => (string) $this->publisher->_id,
        ]);
        $headers = $this->employeeHeaders($pm);

        $order = Order::create([
            'warehouse_id' => (string) $this->warehouse->_id,
            'platform_commission_amount' => 15,
            'status' => 'completed',
        ]);
        app(PublisherCommissionAccountService::class)->accrueForOrder($order);

        $this->getJson(
            "/api/v1/admin/publishers/{$this->publisher->_id}/commission-account",
            $headers
        )
            ->assertOk()
            ->assertJsonPath('data.account.commission_owed', 15);

        $this->postJson(
            "/api/v1/admin/publishers/{$this->publisher->_id}/commission-settlements",
            ['amount' => 5, 'reference' => 'BANK-1'],
            $headers
        )
            ->assertCreated()
            ->assertJsonPath('data.account.commission_owed', 10)
            ->assertJsonPath('data.account.commission_paid_total', 5);

        $this->assertSame(2, PublisherCommissionEntry::query()->count());
    }

    public function test_publisher_manager_cannot_access_other_publisher_account(): void
    {
        $other = Publisher::create(['name' => 'Other']);
        $pm = $this->employee('publisher_manager', [
            'publisher_id' => (string) $this->publisher->_id,
        ]);

        $this->getJson(
            "/api/v1/admin/publishers/{$other->_id}/commission-account",
            $this->employeeHeaders($pm)
        )->assertForbidden();
    }

    public function test_manager_can_read_any_commission_account(): void
    {
        $headers = $this->employeeHeaders($this->employee('manager'));

        $this->getJson(
            "/api/v1/admin/publishers/{$this->publisher->_id}/commission-account",
            $headers
        )
            ->assertOk()
            ->assertJsonPath('data.account.commission_owed', 0);
    }

    public function test_publisher_manager_can_filter_order_totals_for_own_publisher(): void
    {
        $pm = $this->employee('publisher_manager', [
            'publisher_id' => (string) $this->publisher->_id,
        ]);
        $headers = $this->employeeHeaders($pm);

        Order::create([
            'warehouse_id' => (string) $this->warehouse->_id,
            'total' => 12.5,
            'created_at' => new \DateTimeImmutable('2026-10-14T10:00:00Z'),
        ]);
        Order::create([
            'warehouse_id' => (string) $this->warehouse->_id,
            'total' => 7.5,
            'created_at' => new \DateTimeImmutable('2026-10-01T10:00:00Z'),
        ]);
        Order::create([
            'warehouse_id' => (string) Warehouse::create([
                'name' => 'Other WH',
                'publisher_id' => 'another-publisher',
            ])->_id,
            'total' => 99,
            'created_at' => new \DateTimeImmutable('2026-10-14T10:00:00Z'),
        ]);

        $this->getJson(
            "/api/v1/admin/publishers/{$this->publisher->_id}/order-totals?period=day&date=2026-10-14",
            $headers
        )
            ->assertOk()
            ->assertJsonPath('data.total_sum', 12.5)
            ->assertJsonPath('data.orders.total', 1)
            ->assertJsonPath('data.orders.data.0.total', 12.5)
            ->assertJsonMissingPath('data.orders.data.0.items');
    }

    public function test_publisher_manager_can_view_all_time_order_revenue_for_own_publisher_only(): void
    {
        $pm = $this->employee('publisher_manager', [
            'publisher_id' => (string) $this->publisher->_id,
        ]);
        $otherWarehouse = Warehouse::create([
            'name' => 'Other WH',
            'publisher_id' => 'another-publisher',
        ]);
        Order::create([
            'warehouse_id' => (string) $this->warehouse->_id,
            'total' => 12.5,
            'created_at' => new \DateTimeImmutable('2024-01-01T10:00:00Z'),
        ]);
        Order::create([
            'warehouse_id' => (string) $this->warehouse->_id,
            'total' => 7.5,
            'created_at' => new \DateTimeImmutable('2026-10-14T10:00:00Z'),
        ]);
        Order::create([
            'warehouse_id' => (string) $otherWarehouse->_id,
            'total' => 99,
            'created_at' => new \DateTimeImmutable('2026-10-14T10:00:00Z'),
        ]);

        $this->getJson(
            "/api/v1/admin/publishers/{$this->publisher->_id}/order-totals?period=all",
            $this->employeeHeaders($pm)
        )
            ->assertOk()
            ->assertJsonPath('data.total_sum', 20)
            ->assertJsonPath('data.orders.total', 2);
    }

    public function test_publisher_manager_can_only_list_own_publishers_warehouses(): void
    {
        $pm = $this->employee('publisher_manager', [
            'publisher_id' => (string) $this->publisher->_id,
        ]);
        Warehouse::create([
            'name' => 'Other publisher warehouse',
            'publisher_id' => 'another-publisher',
        ]);

        $this->getJson('/api/v1/admin/warehouses?per_page=100', $this->employeeHeaders($pm))
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.name', 'WH');
    }

    public function test_publisher_manager_cannot_view_another_publishers_order_totals(): void
    {
        $other = Publisher::create(['name' => 'Other']);
        $pm = $this->employee('publisher_manager', [
            'publisher_id' => (string) $this->publisher->_id,
        ]);

        $this->getJson(
            "/api/v1/admin/publishers/{$other->_id}/order-totals?period=year&date=2026-01-01",
            $this->employeeHeaders($pm)
        )->assertForbidden();
    }

    public function test_manager_can_filter_admin_order_totals_by_publisher_and_period(): void
    {
        $other = Publisher::create(['name' => 'Another Press']);
        $otherWarehouse = Warehouse::create([
            'name' => 'Another WH',
            'publisher_id' => (string) $other->_id,
        ]);
        Order::create([
            'warehouse_id' => (string) $this->warehouse->_id,
            'total' => 12.5,
            'platform_commission_amount' => 1.25,
            'created_at' => new \DateTimeImmutable('2026-10-14T10:00:00Z'),
        ]);
        Order::create([
            'warehouse_id' => (string) $otherWarehouse->_id,
            'total' => 20,
            'platform_commission_amount' => 2,
            'created_at' => new \DateTimeImmutable('2026-10-14T10:00:00Z'),
        ]);
        Order::create([
            'warehouse_id' => (string) $this->warehouse->_id,
            'total' => 7.5,
            'created_at' => new \DateTimeImmutable('2026-10-01T10:00:00Z'),
        ]);

        $this->getJson(
            "/api/v1/admin/settings/order-totals?period=day&date=2026-10-14&publisher_id={$this->publisher->_id}",
            $this->employeeHeaders($this->employee('manager'))
        )
            ->assertOk()
            ->assertJsonPath('data.total_sum', 12.5)
            ->assertJsonPath('data.commission_sum', 1.25)
            ->assertJsonPath('data.orders.total', 1)
            ->assertJsonPath('data.orders.data.0.total', 12.5)
            ->assertJsonPath('data.orders.data.0.commission', 1.25)
            ->assertJsonPath('data.orders.data.0.publisher_name', 'Ledger Press')
            ->assertJsonMissingPath('data.orders.data.0.customer_name');
    }

    public function test_publisher_manager_cannot_access_admin_order_totals(): void
    {
        $pm = $this->employee('publisher_manager', [
            'publisher_id' => (string) $this->publisher->_id,
        ]);

        $this->getJson(
            '/api/v1/admin/settings/order-totals?period=year&date=2026-01-01',
            $this->employeeHeaders($pm)
        )->assertForbidden();
    }
}
