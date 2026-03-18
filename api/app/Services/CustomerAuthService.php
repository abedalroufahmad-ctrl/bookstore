<?php

namespace App\Services;

use App\Domain\Auth\Interfaces\CustomerAuthServiceInterface;
use App\Models\Customer;
use Illuminate\Support\Facades\Hash;

class CustomerAuthService extends BaseService implements CustomerAuthServiceInterface
{
    public function register(array $data): Customer
    {
        $data['password'] = Hash::make($data['password']);

        return Customer::create($data);
    }

    public function tokenForUser(Customer $customer): string
    {
        return auth('customer')->login($customer);
    }

    public function attemptLogin(array $credentials): string|false
    {
        return auth('customer')->attempt($credentials);
    }

    public function updateProfile(Customer $customer, array $data): Customer
    {
        // Only update allowed fields; do not create a new customer.
        $fillable = array_flip($customer->getFillable());
        $payload = array_intersect_key($data, $fillable);
        // Password is hashed by the model's cast; do not hash here to avoid double-hashing.
        if (array_key_exists('password_confirmation', $data)) {
            unset($payload['password_confirmation']);
        }

        $customer->update($payload);

        return $customer->fresh();
    }
}
