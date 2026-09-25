<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Employee;
use App\Models\Publisher;
use App\Models\Warehouse;

class PublisherSettingsSecurityTest extends MongoFeatureTestCase
{
    private Publisher $publisher;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->publisher = Publisher::create([
            'name' => 'Secret Press',
            'settings' => [
                'payment_methods' => ['cod', 'paypal'],
                'support_email' => 'help@press.test',
                'bank_name' => 'Hidden Bank',
                'bank_account_number' => 'IBAN-SECRET-123',
                'paypal_email' => 'payouts@press.test',
                'paypal_merchant_id' => 'MERCHANT-SECRET',
                'platform_commission_percent' => 12,
            ],
        ]);
        $this->warehouse = Warehouse::create(['name' => 'WH', 'publisher_id' => (string) $this->publisher->_id]);
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

    private function assertNoPayoutSecrets(string $json): void
    {
        foreach (['IBAN-SECRET-123', 'MERCHANT-SECRET', 'payouts@press.test', 'Hidden Bank', 'platform_commission_percent'] as $secret) {
            $this->assertStringNotContainsString($secret, $json, "Public response leaked [{$secret}].");
        }
    }

    public function test_public_publisher_endpoints_hide_payout_settings(): void
    {
        $id = (string) $this->publisher->_id;

        $show = $this->getJson("/api/v1/publishers/{$id}")->assertOk();
        $this->assertNoPayoutSecrets($show->getContent());
        $show->assertJsonPath('data.public_settings.payment_methods', ['cod', 'paypal']);
        $show->assertJsonMissingPath('data.settings');

        $this->assertNoPayoutSecrets($this->getJson('/api/v1/publishers')->assertOk()->getContent());
    }

    public function test_public_book_endpoints_hide_publisher_payout_settings(): void
    {
        $book = Book::create([
            'title' => 'Leaky Book',
            'price' => 5,
            'stock_quantity' => 3,
            'condition' => 'new',
            'is_visible' => true,
            'is_sold' => false,
            'cover_image' => 'https://covers.example.test/book.jpg',
            'warehouse_id' => (string) $this->warehouse->_id,
            'publisher_id' => (string) $this->publisher->_id,
            'publisher_ids' => [(string) $this->publisher->_id],
        ]);

        $list = $this->getJson('/api/v1/books')->assertOk()->assertSee('Leaky Book')->assertSee('Secret Press');
        $this->assertNoPayoutSecrets($list->getContent());

        $show = $this->getJson('/api/v1/books/'.$book->_id)->assertOk()->assertSee('Secret Press');
        $this->assertNoPayoutSecrets($show->getContent());
    }

    public function test_warehouse_manager_cannot_read_publisher_payout_settings(): void
    {
        $wm = $this->employee('warehouse_manager', ['warehouse_ids' => [(string) $this->warehouse->_id]]);

        $this->getJson("/api/v1/admin/publishers/{$this->publisher->_id}/settings", $this->employeeHeaders($wm))
            ->assertForbidden();
    }

    public function test_publisher_manager_can_only_read_own_publisher_settings(): void
    {
        $other = Publisher::create(['name' => 'Other Press', 'settings' => ['bank_account_number' => 'OTHER-IBAN']]);
        $pm = $this->employee('publisher_manager', ['publisher_id' => (string) $this->publisher->_id]);
        $headers = $this->employeeHeaders($pm);

        $this->getJson("/api/v1/admin/publishers/{$this->publisher->_id}/settings", $headers)
            ->assertOk()
            ->assertJsonPath('data.bank_account_number', 'IBAN-SECRET-123');

        $this->getJson("/api/v1/admin/publishers/{$other->_id}/settings", $headers)->assertForbidden();
    }

    public function test_manager_can_read_and_update_settings(): void
    {
        $headers = $this->employeeHeaders($this->employee('manager'));
        $url = "/api/v1/admin/publishers/{$this->publisher->_id}/settings";

        $this->getJson($url, $headers)->assertOk()->assertJsonPath('data.paypal_merchant_id', 'MERCHANT-SECRET');

        $this->putJson($url, ['support_phone' => '+1 555', 'platform_commission_percent' => 15], $headers)
            ->assertOk()
            ->assertJsonPath('data.support_phone', '+1 555')
            ->assertJsonPath('data.platform_commission_percent', 15);
    }

    public function test_publisher_manager_cannot_change_platform_commission(): void
    {
        $pm = $this->employee('publisher_manager', ['publisher_id' => (string) $this->publisher->_id]);

        $this->putJson(
            "/api/v1/admin/publishers/{$this->publisher->_id}/settings",
            ['platform_commission_percent' => 0, 'support_phone' => '123'],
            $this->employeeHeaders($pm)
        )->assertOk();

        $this->assertSame(12, (int) ($this->publisher->fresh()->settings['platform_commission_percent'] ?? null));
    }
}
