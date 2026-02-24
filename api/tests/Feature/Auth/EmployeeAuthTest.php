<?php

namespace Tests\Feature\Auth;

use App\Models\Employee;
use App\Models\Warehouse;
use Tests\ApiTestCase;

class EmployeeAuthTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $warehouse = Warehouse::first() ?? Warehouse::create([
            'name' => 'Test Warehouse',
            'address' => '123 Test St',
            'country' => 'USA',
            'city' => 'Test City',
            'phone' => '+1234567890',
            'email' => 'warehouse@test.test',
        ]);

        $this->employee = Employee::create([
            'name' => 'Test Manager',
            'email' => 'manager@test.test',
            'password' => 'password',
            'role' => 'manager',
            'warehouse_id' => $warehouse->getKey(),
        ]);
    }

    public function test_employee_can_login(): void
    {
        $response = $this->postJson('/api/v1/employees/login', [
            'email' => 'manager@test.test',
            'password' => 'password',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'employee',
                'token',
                'token_type',
                'expires_in',
            ],
        ]);
        $this->assertNotNull($response->json('data.token'));
    }

    public function test_employee_login_fails_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/v1/employees/login', [
            'email' => 'manager@test.test',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('success', false);
    }

    public function test_employee_can_get_me_with_token(): void
    {
        $token = $this->loginAsEmployee('manager@test.test', 'password');

        $response = $this->withToken($token)->getJson('/api/v1/employees/me');

        $response->assertStatus(200);
        $response->assertJsonPath('data.email', 'manager@test.test');
    }

    public function test_employee_cannot_access_me_without_token(): void
    {
        $response = $this->getJson('/api/v1/employees/me');

        // Unauthenticated requests must not succeed
        $this->assertNotEquals(200, $response->status());
    }
}
