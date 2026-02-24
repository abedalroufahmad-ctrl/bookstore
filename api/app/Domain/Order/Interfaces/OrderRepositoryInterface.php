<?php

namespace App\Domain\Order\Interfaces;

use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OrderRepositoryInterface
{
    public function findById(string $id, array $with = []): ?Order;

    public function getByCustomerId(string $customerId, int $perPage = 15): LengthAwarePaginator;

    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function create(array $data): Order;

    public function update(string $id, array $data): bool;
}
