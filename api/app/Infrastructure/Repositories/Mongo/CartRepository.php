<?php

namespace App\Infrastructure\Repositories\Mongo;

use App\Domain\Cart\Enums\CartStatus;
use App\Domain\Cart\Interfaces\CartRepositoryInterface;
use App\Models\Cart;
use App\Models\Customer;

class CartRepository implements CartRepositoryInterface
{
    public function __construct(
        protected Cart $model
    ) {}

    public function findActiveByCustomer(Customer $customer): ?Cart
    {
        return $this->model->newQuery()
            ->where('customer_id', $customer->getKey())
            ->where('status', CartStatus::Active->value)
            ->first();
    }

    public function create(array $data): Cart
    {
        return $this->model->create($data);
    }

    public function update(string $id, array $data): bool
    {
        $model = $this->model->find($id);

        if (! $model) {
            return false;
        }

        return $model->update($data);
    }
}
