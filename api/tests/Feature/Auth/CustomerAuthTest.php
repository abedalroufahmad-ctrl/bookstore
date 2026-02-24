<?php

namespace Tests\Feature\Auth;

use App\Models\Customer;
use Tests\ApiTestCase;

class CustomerAuthTest extends ApiTestCase
{
    public function test_customer_can_register(): void
    {
        $response = $this->postJson('/api/v1/customers/register', [
            'name' => 'New Customer',
            'email' => 'newcustomer' . uniqid() . '@test.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'customer',
                'token',
                'token_type',
                'expires_in',
            ],
        ]);
        $this->assertNotNull($response->json('data.token'));
    }

    public function test_customer_can_login(): void
    {
        $customer = $this->createCustomer([
            'email' => 'login@test.test',
        ]);

        $response = $this->postJson('/api/v1/customers/login', [
            'email' => 'login@test.test',
            'password' => 'password',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.customer.email', 'login@test.test');
    }

    public function test_customer_registration_requires_valid_email(): void
    {
        $response = $this->postJson('/api/v1/customers/register', [
            'name' => 'Test',
            'email' => 'invalid-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422);
    }
}
