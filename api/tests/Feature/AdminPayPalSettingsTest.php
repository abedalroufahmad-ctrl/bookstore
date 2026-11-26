<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Setting;
use App\Services\PayPalService;

class AdminPayPalSettingsTest extends MongoFeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'paypal.mode' => 'sandbox',
            'paypal.client_id' => 'sandbox-client',
            'paypal.secret' => 'sandbox-secret',
            'paypal.sandbox_client_id' => '',
            'paypal.sandbox_secret' => '',
            'paypal.live_client_id' => '',
            'paypal.live_secret' => '',
        ]);
        Setting::set('paypal_mode', 'sandbox');
    }

    private function manager(): Employee
    {
        return Employee::create([
            'name' => 'Manager',
            'email' => uniqid('manager').'@staff.test',
            'password' => 'secret123',
            'role' => 'manager',
        ]);
    }

    public function test_admin_settings_exposes_paypal_mode_without_exposing_credentials(): void
    {
        $headers = $this->employeeHeaders($this->manager());

        $this->getJson('/api/v1/admin/settings', $headers)
            ->assertOk()
            ->assertJsonPath('data.paypal_mode', 'sandbox')
            ->assertJsonPath('data.paypal_available_modes', ['sandbox'])
            ->assertJsonMissing(['sandbox-client', 'sandbox-secret']);

        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonMissingPath('data.paypal_mode')
            ->assertJsonMissingPath('data.paypal_available_modes');
    }

    public function test_manager_can_switch_only_to_a_mode_with_configured_credentials(): void
    {
        $headers = $this->employeeHeaders($this->manager());

        $this->putJson('/api/v1/admin/settings', ['paypal_mode' => 'live'], $headers)
            ->assertUnprocessable();

        $this->assertSame('sandbox', app(PayPalService::class)->mode());

        config([
            'paypal.live_client_id' => 'live-client',
            'paypal.live_secret' => 'live-secret',
        ]);

        $this->putJson('/api/v1/admin/settings', ['paypal_mode' => 'live'], $headers)
            ->assertOk();

        $service = app(PayPalService::class);
        $this->assertSame('live', $service->mode());
        $this->assertSame('https://api-m.paypal.com', $service->baseUrl());
    }

    public function test_manager_can_edit_site_commission_collection_account_without_exposing_it_publicly(): void
    {
        $headers = $this->employeeHeaders($this->manager());

        $this->putJson('/api/v1/admin/settings', [
            'commission_collection_paypal_email' => 'site@example.test',
            'commission_collection_bank_name' => 'Site Bank',
            'commission_collection_bank_account_number' => 'SITE-ACCOUNT-123',
        ], $headers)->assertOk();

        $this->getJson('/api/v1/admin/settings', $headers)
            ->assertOk()
            ->assertJsonPath('data.commission_collection_paypal_email', 'site@example.test')
            ->assertJsonPath('data.commission_collection_bank_name', 'Site Bank')
            ->assertJsonPath('data.commission_collection_bank_account_number', 'SITE-ACCOUNT-123');

        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonMissingPath('data.commission_collection_paypal_email')
            ->assertJsonMissingPath('data.commission_collection_bank_account_number');
    }
}
