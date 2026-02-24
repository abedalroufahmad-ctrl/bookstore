<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\WarehouseStoreRequest;
use App\Http\Requests\Admin\WarehouseUpdateRequest;
use App\Infrastructure\Services\WarehouseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseController extends BaseApiController
{
    public function __construct(
        protected WarehouseService $warehouseService
    ) {}

    public function index(Request $request): JsonResponse
    {
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
        $warehouse = $this->warehouseService->create($request->validated());

        return $this->successResponse($warehouse->fresh(), 'Warehouse created', 201);
    }

    public function show(string $id): JsonResponse
    {
        $warehouse = $this->warehouseService->getById($id);

        if (! $warehouse) {
            return $this->errorResponse('Warehouse not found', 404);
        }

        return $this->successResponse($warehouse);
    }

    public function update(WarehouseUpdateRequest $request, string $id): JsonResponse
    {
        $warehouse = $this->warehouseService->update($id, $request->validated());

        if (! $warehouse) {
            return $this->errorResponse('Warehouse not found', 404);
        }

        return $this->successResponse($warehouse, 'Warehouse updated');
    }

    public function destroy(string $id): JsonResponse
    {
        if (! $this->warehouseService->delete($id)) {
            return $this->errorResponse('Warehouse not found', 404);
        }

        return $this->successResponse(null, 'Warehouse deleted');
    }
}
