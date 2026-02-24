<?php

namespace App\Infrastructure\Repositories\Mongo;

use App\Domain\Customer\Interfaces\CustomerRepositoryInterface;
use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomerRepository implements CustomerRepositoryInterface
{
    public function __construct(
        protected Customer $model
    ) {}

    public function findById(string $id): ?Customer
    {
        return $this->model->find($id);
    }

    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->newQuery();

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['country'])) {
            $query->where('country', $filters['country']);
        }

        if (! empty($filters['city'])) {
            $query->where('city', $filters['city']);
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }
}
