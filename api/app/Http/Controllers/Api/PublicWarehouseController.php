<?php

namespace App\Http\Controllers\Api;

use App\Infrastructure\Services\CachedCatalogService;
use App\Infrastructure\Services\WarehouseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicWarehouseController extends BaseApiController
{
    public function __construct(
        protected CachedCatalogService $catalogService,
        protected WarehouseService $warehouseService
    ) {}

    /**
     * Public warehouse list (browsing).
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->filled('search') ? ['search' => $request->get('search')] : [];
        $perPage = min((int) $request->get('per_page', 32), 100);

        $warehouses = $this->catalogService->getCachedWarehouses($filters, $perPage);

        return $this->successResponse($warehouses);
    }

    public function show(string $id): JsonResponse
    {
        $warehouse = $this->warehouseService->getById($id, []);

        if (! $warehouse) {
            return $this->errorResponse('Warehouse not found', 404);
        }

        return $this->successResponse($warehouse);
    }
}
