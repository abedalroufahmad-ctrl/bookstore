<?php

namespace Tests;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

abstract class ApiTestCase extends TestCase
{
    /**
     * Login as employee and return JWT token.
     */
    protected function loginAsEmployee(string $email = 'manager@bookstore.test', string $password = 'password'): string
    {
        $response = $this->postJson('/api/v1/employees/login', [
            'email' => $email,
            'password' => $password,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        return $response->json('data.token');
    }

    /**
     * Login as customer and return JWT token.
     */
    protected function loginAsCustomer(string $email, string $password): string
    {
        $response = $this->postJson('/api/v1/customers/login', [
            'email' => $email,
            'password' => $password,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        return $response->json('data.token');
    }

    /**
     * Assert API success response structure.
     */
    protected function assertApiSuccess($response): void
    {
        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'message', 'data']);
        $response->assertJsonPath('success', true);
    }

    /**
     * Assert API error response.
     */
    protected function assertApiError($response, int $status = 422, ?string $message = null): void
    {
        $response->assertStatus($status);
        $response->assertJsonStructure(['success', 'message', 'data']);
        $response->assertJsonPath('success', false);
        if ($message !== null) {
            $response->assertJsonPath('message', $message);
        }
    }

    /**
     * Create a test employee.
     */
    protected function createEmployee(array $overrides = []): Employee
    {
        $warehouse = Warehouse::first() ?? Warehouse::create([
            'name' => 'Test Warehouse',
            'address' => '123 Test St',
            'country' => 'USA',
            'city' => 'Test City',
            'phone' => '+1234567890',
            'email' => 'warehouse@test.test',
        ]);

        return Employee::create(array_merge([
            'name' => 'Test Employee',
            'email' => 'employee' . uniqid() . '@test.test',
            'password' => 'password',
            'role' => 'manager',
            'warehouse_id' => $warehouse->getKey(),
        ], $overrides));
    }

    /**
     * Create a test customer.
     */
    protected function createCustomer(array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            'name' => 'Test Customer',
            'email' => 'customer' . uniqid() . '@test.test',
            'password' => 'password',
            'address' => '123 Test St',
            'country' => 'USA',
            'city' => 'Test City',
        ], $overrides));
    }
}
