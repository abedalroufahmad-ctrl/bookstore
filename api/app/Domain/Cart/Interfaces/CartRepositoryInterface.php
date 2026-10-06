<?php

namespace App\Domain\Cart\Interfaces;

use App\Models\Cart;
use App\Models\Customer;

interface CartRepositoryInterface
{
    public function findActiveByCustomer(Customer $customer): ?Cart;

    public function create(array $data): Cart;

    public function update(string $id, array $data): bool;
}
