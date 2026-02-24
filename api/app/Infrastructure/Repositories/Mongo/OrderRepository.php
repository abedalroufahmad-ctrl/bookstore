<?php

namespace App\Infrastructure\Repositories\Mongo;

use App\Domain\Order\Interfaces\OrderRepositoryInterface;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrderRepository implements OrderRepositoryInterface
{
    public function __construct(
        protected Order $model
    ) {}

    public function findById(string $id, array $with = []): ?Order
    {
        $query = $this->model->newQuery();

        if (! empty($with)) {
            $query->with($with);
        }

        return $query->find($id);
    }

    public function getByCustomerId(string $customerId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->newQuery()->with($filters['with'] ?? ['customer', 'employee']);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $customerIds = \App\Models\Customer::where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->limit(500)
                ->pluck('_id');
            $query->where(function ($q) use ($search, $customerIds) {
                $q->where('status', 'like', "%{$search}%");
                if ($customerIds->isNotEmpty()) {
                    $q->orWhereIn('customer_id', $customerIds);
                }
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (! empty($filters['assigned_to_me'])) {
            $query->where('employee_id', auth('employee')?->id());
        }

        if (! empty($filters['unassigned'])) {
            $query->whereNull('employee_id');
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function create(array $data): Order
    {
        return $this->model->create($data);
    }

    public function update(string $id, array $data): bool
    {
        $model = $this->findById($id);

        if (! $model) {
            return false;
        }

        return $model->update($data);
    }
}
