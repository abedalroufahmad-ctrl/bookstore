<?php

namespace Tests\Feature;

use App\Models\Employee;

class RateLimitingTest extends MongoFeatureTestCase
{
    public function test_login_is_throttled_per_email_without_locking_out_other_accounts(): void
    {
        config(['rate_limits.login_per_minute' => 3, 'rate_limits.login_ip_per_minute' => 50]);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/employees/login', ['email' => 'victim@staff.test', 'password' => 'wrong-pass'])
                ->assertStatus(401);
        }

        $this->postJson('/api/v1/employees/login', ['email' => 'victim@staff.test', 'password' => 'wrong-pass'])
            ->assertStatus(429);

        $this->postJson('/api/v1/employees/login', ['email' => 'other@staff.test', 'password' => 'wrong-pass'])
            ->assertStatus(401);
    }

    public function test_signed_in_users_get_their_own_bucket_instead_of_the_shared_ip_bucket(): void
    {
        config(['rate_limits.guest_per_minute' => 2, 'rate_limits.user_per_minute' => 50]);

        $this->getJson('/api/v1/categories')->assertOk();
        $this->getJson('/api/v1/categories')->assertOk();
        $this->getJson('/api/v1/categories')->assertStatus(429);

        $manager = Employee::create([
            'name' => 'M', 'email' => 'm@staff.test', 'password' => 'secret123', 'role' => 'manager',
        ]);
        $this->getJson('/api/v1/categories', $this->employeeHeaders($manager))->assertOk();
    }

    public function test_forged_bearer_token_falls_back_to_guest_limit(): void
    {
        config(['rate_limits.guest_per_minute' => 1, 'rate_limits.user_per_minute' => 50]);
        $headers = ['Authorization' => 'Bearer not-a-real-token', 'Accept' => 'application/json'];

        $this->getJson('/api/v1/categories', $headers)->assertOk();
        $this->getJson('/api/v1/categories', $headers)->assertStatus(429);
    }
}
