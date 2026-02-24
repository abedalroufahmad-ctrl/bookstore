<?php

namespace App\Domain\Auth\Interfaces;

use App\Models\Customer;

interface CustomerAuthServiceInterface
{
    /**
     * Register a new customer.
     */
    public function register(array $data): Customer;

    /**
     * Generate JWT token for a customer.
     */
    public function tokenForUser(Customer $customer): string;

    /**
     * Attempt to authenticate a customer and return JWT token.
     */
    public function attemptLogin(array $credentials): string|false;

    /**
     * Update customer profile.
     */
    public function updateProfile(Customer $customer, array $data): Customer;
}
