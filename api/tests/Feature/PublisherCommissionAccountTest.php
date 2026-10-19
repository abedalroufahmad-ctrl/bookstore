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
}
