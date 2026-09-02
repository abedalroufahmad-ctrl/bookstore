<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Auth\Enums\UserRole;
use App\Http\Controllers\Api\BaseApiController;
use App\Infrastructure\Services\BookService;
use App\Models\Order;
use App\Models\Warehouse;
use App\Services\OrderService;
use App\Services\PosReportAggregator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosController extends BaseApiController
{
    public function __construct(
        protected OrderService $orderService,
        protected BookService $bookService,
        protected PosReportAggregator $posReportAggregator
    ) {}

    /**
     * Sellable catalog for the POS terminal.
     * Direct-sales staff default to their warehouse in the client, but may
     * request any warehouse / publisher. Other roles stay scoped.
     */
    public function books(Request $request): JsonResponse
    {
        $employee = auth('employee')->user();
        $filters = [
            'in_stock' => true,
            'is_visible' => true,
            'is_sold' => false,
        ];
        if ($request->filled('search') && is_string($request->get('search'))) {
            $filters['search'] = $request->get('search');
        }
        if ($request->filled('warehouse_id') && is_string($request->get('warehouse_id'))) {
            $filters['warehouse_id'] = $request->get('warehouse_id');
        }
        if ($request->filled('publisher_id') && is_string($request->get('publisher_id'))) {
            $filters['publisher_id'] = $request->get('publisher_id');
        }

        if ($employee && UserRole::isWarehouseScoped($employee->role)) {
            $managedIds = $employee->getManagedWarehouseIds();
            $filters['warehouse_ids'] = empty($managedIds) ? ['__none__'] : $managedIds;
        } elseif ($employee && UserRole::isPublisherScoped($employee->role)) {
            $filters['publisher_id'] = $employee->getManagedPublisherId() ?? '__none__';
        }

        $filters['with'] = ['authors', 'warehouse', 'publisher', 'category'];
        $perPage = min((int) $request->get('per_page', 24), 100);
        $books = $this->bookService->getAll($filters, $perPage);

        return $this->successResponse($books);
    }

    public function createInvoice(Request $request): JsonResponse
    {
        $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.book_id' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'warehouse_id' => ['required', 'string'],
            'customer_name' => ['nullable', 'string', 'max:255'],
        ]);

        $employee = auth('employee')->user();
        $warehouseId = (string) $request->get('warehouse_id');
        $warehouse = Warehouse::find($warehouseId);
        if (! $warehouse) {
            return $this->errorResponse('Warehouse not found.', 404);
        }

        if ($deny = $this->forbidWarehouseForSale($employee, $warehouse)) {
            return $deny;
        }

        $customerName = trim((string) $request->get('customer_name', ''));

        try {
            $order = $this->orderService->createPosInvoice(
                $request->get('items'),
                $warehouseId,
                $employee?->getKey(),
                $customerName !== '' ? $customerName : null
            );

            return $this->successResponse($order, 'Invoice created successfully', 201);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->errorResponse(config('app.debug') ? $e->getMessage() : 'Failed to create invoice.', 500);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $employee = auth('employee')->user();
        $query = $this->scopedInvoiceQuery($employee);

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', (string) $request->get('warehouse_id'));
        }
        if ($request->filled('search')) {
            $search = (string) $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', '%'.$search.'%')
                    ->orWhere('_id', $search);
            });
        }

        $perPage = min((int) $request->get('per_page', 25), 100);
        $orders = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->successResponse($orders);
    }

    public function show(string $id): JsonResponse
    {
        $employee = auth('employee')->user();
        $order = $this->scopedInvoiceQuery($employee)->find($id);
        if (! $order) {
            return $this->errorResponse('Invoice not found', 404);
        }

        $order = $this->orderService->getOrderById((string) $order->getKey(), null, ['warehouse.publisher', 'employee']);
        if (! $order) {
            return $this->errorResponse('Invoice not found', 404);
        }

        return $this->successResponse($order);
    }

    public function reports(Request $request): JsonResponse
    {
        $employee = auth('employee')->user();
        $query = $this->scopedInvoiceQuery($employee);

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', (string) $request->get('warehouse_id'));
        }

        $type = $request->get('type', 'daily');
        $timezone = $this->posReportAggregator->resolveTimezone(
            $request->get('timezone'),
            $request->get('utc_offset_minutes')
        );

        $orders = $query->get(['total', 'created_at']);
        $aggregated = $this->posReportAggregator->aggregate(
            $orders,
            is_string($type) ? $type : 'daily',
            $timezone
        );

        return $this->successResponse($aggregated);
    }

    private function scopedInvoiceQuery($employee)
    {
        $query = Order::where('is_direct_sale', true);

        if (! $employee) {
            return $query->where('_id', '__none__');
        }

        if ($employee->role === UserRole::Manager->value) {
            return $query;
        }

        if ($employee->role === UserRole::DirectSales->value) {
            $managedIds = $employee->getManagedWarehouseIds();
            $query->where(function ($q) use ($employee, $managedIds) {
                $q->where('employee_id', (string) $employee->getKey());
                if (! empty($managedIds)) {
                    $q->orWhereIn('warehouse_id', $managedIds);
                }
            });

            return $query;
        }

        if (UserRole::isWarehouseScoped($employee->role)) {
            $managedIds = $employee->getManagedWarehouseIds();
            if (empty($managedIds)) {
                return $query->where('_id', '__none__');
            }

            return $query->whereIn('warehouse_id', $managedIds);
        }

        if (UserRole::isPublisherScoped($employee->role)) {
            $pubId = $employee->getManagedPublisherId();
            if (! $pubId) {
                return $query->where('_id', '__none__');
            }
            $whIds = Warehouse::where('publisher_id', $pubId)->pluck('_id')->map(fn ($id) => (string) $id)->all();
            if (empty($whIds)) {
                return $query->where('_id', '__none__');
            }

            return $query->whereIn('warehouse_id', $whIds);
        }

        return $query->where('_id', '__none__');
    }

    private function forbidWarehouseForSale($employee, Warehouse $warehouse): ?JsonResponse
    {
        if (! $employee) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $warehouseId = (string) $warehouse->getKey();

        if ($employee->role === UserRole::Manager->value || $employee->role === UserRole::DirectSales->value) {
            return null;
        }

        if (UserRole::isWarehouseScoped($employee->role) && ! $employee->managesWarehouse($warehouseId)) {
            return $this->errorResponse('Forbidden. You can only create invoices for your assigned warehouses.', 403);
        }

        if (UserRole::isPublisherScoped($employee->role)) {
            $pubId = $employee->getManagedPublisherId();
            if (! $pubId || (string) $warehouse->publisher_id !== $pubId) {
                return $this->errorResponse('Forbidden. You can only create invoices for your publisher\'s warehouses.', 403);
            }
        }

        return null;
    }
}
