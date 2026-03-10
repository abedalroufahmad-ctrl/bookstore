<?php

namespace App\Http\Controllers\Api;

use App\Domain\Auth\Enums\UserRole;
use App\Domain\Order\Interfaces\OrderServiceInterface;
use App\Http\Requests\Order\UpdateOrderStatusRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeOrderController extends BaseApiController
{
    public function __construct(
        private OrderServiceInterface $orderService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = [
            'assigned_to_me' => $request->boolean('assigned_to_me'),
        ];
        $employee = auth('employee')->user();
        if ($employee && UserRole::isWarehouseScoped($employee->role)) {
            $managedIds = $employee->getManagedWarehouseIds();
            if (! empty($managedIds)) {
                $filters['warehouse_ids'] = $managedIds;
            } else {
                $filters['warehouse_id'] = $employee->warehouse_id;
            }
        }
        $perPage = min((int) $request->get('per_page', 15), 100);

        $orders = $this->orderService->getOrdersForEmployee($filters, $perPage);

        return $this->successResponse($orders);
    }

    public function show(string $id): JsonResponse
    {
        $order = $this->orderService->getOrderById($id, null, ['customer', 'employee']);

        if (! $order) {
            return $this->errorResponse('Order not found', 404);
        }

        $employee = auth('employee')->user();
        if ($employee && UserRole::isWarehouseScoped($employee->role)) {
            $orderWarehouseId = $order->warehouse_id ?? $order->employee?->warehouse_id ?? null;
            if ($orderWarehouseId !== null && ! $employee->managesWarehouse((string) $orderWarehouseId)) {
                return $this->errorResponse('Forbidden. Order does not belong to your warehouses.', 403);
            }
        }

        return $this->successResponse($order);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, string $id): JsonResponse
    {
        try {
            $order = $this->orderService->getOrderById($id, null, ['employee']);

            if (! $order) {
                return $this->errorResponse('Order not found', 404);
            }

            $employee = auth('employee')->user();
            if ($employee && UserRole::isWarehouseScoped($employee->role)) {
                $orderWarehouseId = $order->warehouse_id ?? $order->employee?->warehouse_id ?? null;
                if ($orderWarehouseId !== null && ! $employee->managesWarehouse((string) $orderWarehouseId)) {
                    return $this->errorResponse('Forbidden. Order does not belong to your warehouses.', 403);
                }
            }

            $order = $this->orderService->updateStatus($order, $request->validated('status'));

            return $this->successResponse($order, 'Order status updated');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }
}
