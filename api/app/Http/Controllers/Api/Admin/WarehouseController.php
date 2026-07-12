<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Auth\Enums\UserRole;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\WarehouseStoreRequest;
use App\Http\Requests\Admin\WarehouseUpdateRequest;
use App\Infrastructure\Services\WarehouseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class WarehouseController extends BaseApiController
{
    public function __construct(
        protected WarehouseService $warehouseService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $employee = auth('employee')->user();
        // Only global managers see all warehouses; every other employee sees only assigned warehouse(s).
        if ($employee && UserRole::isLimitedToAssignedWarehouses($employee->role)) {
            $managedIds = $employee->getManagedWarehouseIds();
            if (empty($managedIds)) {
                $paginator = new LengthAwarePaginator([], 0, 15, 1, ['path' => $request->url()]);

                return $this->successResponse($paginator);
            }
            $warehouses = [];
            foreach ($managedIds as $wid) {
                $w = $this->warehouseService->getById($wid);
                if ($w) {
                    $warehouses[] = $w;
                }
            }
            if ($search = $request->get('search')) {
                $needle = mb_strtolower((string) $search);
                $warehouses = array_values(array_filter($warehouses, function ($w) use ($needle) {
                    $fields = [
                        $w->name ?? '',
                        $w->email ?? '',
                        $w->city ?? '',
                        $w->country ?? '',
                        $w->address ?? '',
                        $w->phone ?? '',
                    ];
                    foreach ($fields as $f) {
                        if (str_contains(mb_strtolower((string) $f), $needle)) {
                            return true;
                        }
                    }

                    return false;
                }));
            }
            $paginator = new LengthAwarePaginator(
                $warehouses,
                count($warehouses),
                15,
                1,
                ['path' => $request->url()]
            );

            return $this->successResponse($paginator);
        }

        $filters = [
            'search' => $request->get('search'),
            'country' => $request->get('country'),
            'city' => $request->get('city'),
            'publisher_id' => $request->get('publisher_id'),
        ];

        // Publisher managers only see warehouses belonging to their publisher.
        if ($employee && UserRole::isPublisherScoped($employee->role)) {
            $filters['publisher_id'] = $employee->getManagedPublisherId() ?? '__none__';
        }

        $perPage = min((int) $request->get('per_page', 15), 100);

        $warehouses = $this->warehouseService->getAll($filters, $perPage);

        return $this->successResponse($warehouses);
    }

    public function store(WarehouseStoreRequest $request): JsonResponse
    {
        $employee = auth('employee')->user();
        if ($employee && UserRole::isLimitedToAssignedWarehouses($employee->role)) {
            return $this->errorResponse('Forbidden. Only managers can create warehouses.', 403);
        }

        $data = $request->validated();

        // Publisher managers can only create warehouses under their own publisher.
        if ($employee && UserRole::isPublisherScoped($employee->role)) {
            $publisherId = $employee->getManagedPublisherId();
            if (! $publisherId) {
                return $this->errorResponse('Forbidden. You are not assigned to a publisher.', 403);
            }
            $data['publisher_id'] = $publisherId;
        }

        $warehouse = $this->warehouseService->create($data);

        return $this->successResponse($warehouse->fresh(), 'Warehouse created', 201);
    }

    public function show(string $id): JsonResponse
    {
        $warehouse = $this->warehouseService->getById($id);

        if (! $warehouse) {
            return $this->errorResponse('Warehouse not found', 404);
        }

        $employee = auth('employee')->user();
        if ($employee && UserRole::isLimitedToAssignedWarehouses($employee->role)) {
            if (! $employee->managesWarehouse((string) $warehouse->getKey())) {
                return $this->errorResponse('Forbidden. You can only access your assigned warehouses.', 403);
            }
        }
        if ($employee && UserRole::isPublisherScoped($employee->role)) {
            if (! $employee->managesPublisher((string) ($warehouse->publisher_id ?? ''))) {
                return $this->errorResponse('Forbidden. You can only access your publisher\'s warehouses.', 403);
            }
        }

        return $this->successResponse($warehouse);
    }

    public function update(WarehouseUpdateRequest $request, string $id): JsonResponse
    {
        $employee = auth('employee')->user();
        if ($employee && UserRole::isLimitedToAssignedWarehouses($employee->role)) {
            if (! $employee->managesWarehouse($id)) {
                return $this->errorResponse('Forbidden. You can only update your assigned warehouses.', 403);
            }
        }

        $data = $request->validated();

        // Publisher managers can only update their own publisher's warehouses,
        // and cannot move a warehouse to a different publisher.
        if ($employee && UserRole::isPublisherScoped($employee->role)) {
            $existing = $this->warehouseService->getById($id);
            if (! $existing) {
                return $this->errorResponse('Warehouse not found', 404);
            }
            if (! $employee->managesPublisher((string) ($existing->publisher_id ?? ''))) {
                return $this->errorResponse('Forbidden. You can only update your publisher\'s warehouses.', 403);
            }
            $data['publisher_id'] = $employee->getManagedPublisherId();
        }

        $warehouse = $this->warehouseService->update($id, $data, auth('employee')->user());

        if (! $warehouse) {
            return $this->errorResponse('Warehouse not found', 404);
        }

        return $this->successResponse($warehouse, 'Warehouse updated');
    }

    public function destroy(string $id): JsonResponse
    {
        $employee = auth('employee')->user();
        if ($employee && UserRole::isLimitedToAssignedWarehouses($employee->role)) {
            return $this->errorResponse('Forbidden. Only managers can delete warehouses.', 403);
        }
        if ($employee && UserRole::isPublisherScoped($employee->role)) {
            $existing = $this->warehouseService->getById($id);
            if (! $existing) {
                return $this->errorResponse('Warehouse not found', 404);
            }
            if (! $employee->managesPublisher((string) ($existing->publisher_id ?? ''))) {
                return $this->errorResponse('Forbidden. You can only delete your publisher\'s warehouses.', 403);
            }
        }

        if (! $this->warehouseService->delete($id)) {
            return $this->errorResponse('Warehouse not found', 404);
        }

        return $this->successResponse(null, 'Warehouse deleted');
    }
}
