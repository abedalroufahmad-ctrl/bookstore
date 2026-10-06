<?php

namespace App\Infrastructure\Repositories\Mongo;

use App\Domain\Customer\Interfaces\CustomerRepositoryInterface;
use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use MongoDB\BSON\ObjectId;

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
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('country', 'like', "%{$search}%")
                    ->orWhere('postal_code', 'like', "%{$search}%");
                // Match by document id when the query looks like a MongoDB ObjectId (24 hex chars).
                if (strlen($search) === 24 && ctype_xdigit($search)) {
                    try {
                        $q->orWhere('_id', new ObjectId($search));
                    } catch (\Throwable) {
                        // ignore invalid ObjectId
                    }
                }
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

    public function updateById(string $id, array $data): ?Customer
    {
        $customer = $this->findById($id);
        if (! $customer) {
            return null;
        }

        $customer->update($data);

        return $customer->fresh();
    }

    public function deleteById(string $id): bool
    {
        $customer = $this->findById($id);
        if (! $customer) {
            return false;
        }

        return (bool) $customer->delete();
    }
}
