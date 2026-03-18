<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\WarehouseStoreRequest;
use App\Http\Requests\Admin\WarehouseUpdateRequest;
use App\Domain\Auth\Enums\UserRole;
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
        // Managers can see and manage all warehouses; only warehouse_manager is scoped.
        if ($employee && ! UserRole::canManageAllWarehouses($employee->role) && UserRole::isWarehouseScoped($employee->role)) {
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
        ];
        $perPage = min((int) $request->get('per_page', 15), 100);

        $warehouses = $this->warehouseService->getAll($filters, $perPage);

        return $this->successResponse($warehouses);
    }

    public function store(WarehouseStoreRequest $request): JsonResponse
    {
        $employee = auth('employee')->user();
        if ($employee && ! UserRole::canManageAllWarehouses($employee->role) && UserRole::isWarehouseScoped($employee->role)) {
            return $this->errorResponse('Forbidden. Warehouse managers cannot create warehouses.', 403);
        }

        $warehouse = $this->warehouseService->create($request->validated());

        return $this->successResponse($warehouse->fresh(), 'Warehouse created', 201);
    }

    public function show(string $id): JsonResponse
    {
        $warehouse = $this->warehouseService->getById($id);

        if (! $warehouse) {
            return $this->errorResponse('Warehouse not found', 404);
        }

        $employee = auth('employee')->user();
        if ($employee && ! UserRole::canManageAllWarehouses($employee->role) && UserRole::isWarehouseScoped($employee->role)) {
            if (! $employee->managesWarehouse((string) $warehouse->getKey())) {
                return $this->errorResponse('Forbidden. You can only access your assigned warehouses.', 403);
            }
        }

        return $this->successResponse($warehouse);
    }

    public function update(WarehouseUpdateRequest $request, string $id): JsonResponse
    {
        $employee = auth('employee')->user();
        if ($employee && ! UserRole::canManageAllWarehouses($employee->role) && UserRole::isWarehouseScoped($employee->role)) {
            if (! $employee->managesWarehouse($id)) {
                return $this->errorResponse('Forbidden. You can only update your assigned warehouses.', 403);
            }
        }

        $warehouse = $this->warehouseService->update($id, $request->validated(), auth('employee')->user());

        if (! $warehouse) {
            return $this->errorResponse('Warehouse not found', 404);
        }

        return $this->successResponse($warehouse, 'Warehouse updated');
    }

    public function destroy(string $id): JsonResponse
    {
        $employee = auth('employee')->user();
        if ($employee && ! UserRole::canManageAllWarehouses($employee->role) && UserRole::isWarehouseScoped($employee->role)) {
            return $this->errorResponse('Forbidden. Warehouse managers cannot delete warehouses.', 403);
        }

        if (! $this->warehouseService->delete($id)) {
            return $this->errorResponse('Warehouse not found', 404);
        }

        return $this->successResponse(null, 'Warehouse deleted');
    }
}
