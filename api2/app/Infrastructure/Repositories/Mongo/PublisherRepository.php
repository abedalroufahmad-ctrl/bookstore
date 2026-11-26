<?php

namespace App\Infrastructure\Repositories\Mongo;

use App\Domain\Publisher\Interfaces\PublisherRepositoryInterface;
use App\Models\Publisher;
use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PublisherRepository implements PublisherRepositoryInterface
{
    public function __construct(
        protected Publisher $model
    ) {}

    public function findById(string $id, array $with = []): ?Publisher
    {
        $query = $this->model->newQuery();

        if (! empty($with)) {
            $query->with($with);
        }

        return $query->find($id);
    }

    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->newQuery();

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('name', 'like', "%{$search}%");
        }

        $paginator = $query->orderBy('name')->paginate($perPage);
        $publisherIds = collect($paginator->items())
            ->map(fn (Publisher $publisher) => (string) $publisher->getKey())
            ->all();

        if (! empty($publisherIds)) {
            $counts = Warehouse::query()
                ->whereIn('publisher_id', $publisherIds)
                ->get(['publisher_id'])
                ->groupBy('publisher_id')
                ->map(fn ($group) => $group->count());

            foreach ($paginator->items() as $publisher) {
                $publisher->setAttribute(
                    'warehouses_count',
                    $counts[(string) $publisher->getKey()] ?? 0
                );
            }
        }

        return $paginator;
    }

    public function create(array $data): Publisher
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

    public function delete(string $id): bool
    {
        $model = $this->findById($id);

        if (! $model) {
            return false;
        }

        return $model->delete();
    }
}
