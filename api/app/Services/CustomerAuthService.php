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
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $customer->update($data);

        return $customer->fresh();
    }
}
