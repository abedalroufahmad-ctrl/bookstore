<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Auth\Enums\UserRole;
use App\Domain\Order\Interfaces\OrderServiceInterface;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\AssignOrderRequest;
use App\Http\Requests\Admin\BulkDeleteOrdersRequest;
use App\Http\Requests\Order\SubmitWarehouseOrderQuoteRequest;
use App\Http\Requests\Order\UpdateOrderStatusRequest;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOrderController extends BaseApiController
{
    public function __construct(
        private OrderServiceInterface $orderService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->get('search'),
            'status' => $request->get('status'),
            'payment_status' => $request->get('payment_status'),
            'employee_id' => $request->get('employee_id'),
            'unassigned' => $request->boolean('unassigned'),
        ];

        $employee = auth('employee')->user();
        if ($employee) {
            if ($employee->role === \App\Domain\Auth\Enums\UserRole::Shipping->value) {
                // Shipping can only see shipping-related orders
                $filters['status_in'] = [
                    \App\Domain\Order\Enums\OrderStatus::ResubmittedToWarehouse->value,
                    \App\Domain\Order\Enums\OrderStatus::ProcessingFulfillment->value,
                    \App\Domain\Order\Enums\OrderStatus::ShippedCollectingPayment->value,
                    \App\Domain\Order\Enums\OrderStatus::Completed->value,
                ];
            }

            if (\App\Domain\Auth\Enums\UserRole::isOrderWarehouseScoped($employee->role)) {
                $managedIds = $employee->getManagedWarehouseIds();
                if (! empty($managedIds)) {
                    $filters['warehouse_ids'] = $managedIds;
                } else {
                    $filters['warehouse_ids'] = ['__none__'];
                }
            } elseif ($employee->role === \App\Domain\Auth\Enums\UserRole::PublisherManager->value) {
                $pubId = $employee->getManagedPublisherId();
                if ($pubId) {
                    $managedIds = \App\Models\Warehouse::where('publisher_id', $pubId)->pluck('_id')->map(fn ($id) => (string) $id)->toArray();
                    if (! empty($managedIds)) {
                        $filters['warehouse_ids'] = $managedIds;
                    } else {
                        $filters['warehouse_ids'] = ['__none__'];
                    }
                } else {
                    $filters['warehouse_ids'] = ['__none__'];
                }
            }
        }

        $this->applyOptionalOrderFilters($request, $filters);

        $perPage = min((int) $request->get('per_page', 15), 100);

        $orders = $this->orderService->getOrdersForAdmin($filters, $perPage);

        return $this->successResponse($orders);
    }

    /**
     * Apply optional UI filters (warehouse / publisher / direct sales),
     * intersecting with any role-based warehouse scope already on $filters.
     *
     * @param  array<string, mixed>  $filters
     */
    private function applyOptionalOrderFilters(Request $request, array &$filters): void
    {
        if ($request->filled('publisher_id')) {
            $publisherId = (string) $request->get('publisher_id');
            $publisherWarehouseIds = \App\Models\Warehouse::where('publisher_id', $publisherId)
                ->pluck('_id')
                ->map(fn ($id) => (string) $id)
                ->all();

            if (empty($publisherWarehouseIds)) {
                $filters['warehouse_ids'] = ['__none__'];
                unset($filters['warehouse_id']);
            } elseif (! empty($filters['warehouse_ids']) && is_array($filters['warehouse_ids'])) {
                $intersected = array_values(array_intersect($filters['warehouse_ids'], $publisherWarehouseIds));
                $filters['warehouse_ids'] = $intersected !== [] ? $intersected : ['__none__'];
                unset($filters['warehouse_id']);
            } elseif (! empty($filters['warehouse_id'])) {
                if (! in_array((string) $filters['warehouse_id'], $publisherWarehouseIds, true)) {
                    $filters['warehouse_ids'] = ['__none__'];
                    unset($filters['warehouse_id']);
                }
            } else {
                $filters['warehouse_ids'] = $publisherWarehouseIds;
            }
        }

        if ($request->filled('warehouse_id')) {
            $warehouseId = (string) $request->get('warehouse_id');
            if (! empty($filters['warehouse_ids']) && is_array($filters['warehouse_ids'])) {
                if (in_array($warehouseId, $filters['warehouse_ids'], true)) {
                    $filters['warehouse_id'] = $warehouseId;
                    unset($filters['warehouse_ids']);
                } else {
                    $filters['warehouse_ids'] = ['__none__'];
                    unset($filters['warehouse_id']);
                }
            } else {
                $filters['warehouse_id'] = $warehouseId;
            }
        }

        if ($request->has('is_direct_sale') && $request->get('is_direct_sale') !== '' && $request->get('is_direct_sale') !== null) {
            $raw = $request->get('is_direct_sale');
            if (is_bool($raw)) {
                $filters['is_direct_sale'] = $raw;
            } elseif (is_numeric($raw)) {
                $filters['is_direct_sale'] = ((int) $raw) === 1;
            } else {
                $normalized = strtolower(trim((string) $raw));
                if (in_array($normalized, ['1', 'true', 'yes'], true)) {
                    $filters['is_direct_sale'] = true;
                } elseif (in_array($normalized, ['0', 'false', 'no'], true)) {
                    $filters['is_direct_sale'] = false;
                }
            }
        }
    }

    public function show(string $id): JsonResponse
    {
        $order = $this->orderService->getOrderById($id, null, ['customer', 'employee']);

        if (! $order) {
            return $this->errorResponse('Order not found', 404);
        }

        if ($deny = $this->forbidIfOutsideWarehouseScope($order)) {
            return $deny;
        }

        return $this->successResponse($order);
    }

    public function submitWarehouseQuote(SubmitWarehouseOrderQuoteRequest $request, string $id): JsonResponse
    {
        try {
            $order = $this->orderService->getOrderById($id, null, ['employee']);

            if (! $order) {
                return $this->errorResponse('Order not found', 404);
            }

            if ($deny = $this->forbidIfOutsideWarehouseScope($order)) {
                return $deny;
            }

            $order = $this->orderService->submitWarehouseQuote($order, $request->validated());

            return $this->successResponse($order, 'Warehouse quote saved.');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function updateStatus(UpdateOrderStatusRequest $request, string $id): JsonResponse
    {
        try {
            $order = $this->orderService->getOrderById($id, null, ['employee']);

            if (! $order) {
                return $this->errorResponse('Order not found', 404);
            }

            if ($deny = $this->forbidIfOutsideWarehouseScope($order)) {
                return $deny;
            }

            $order = $this->orderService->updateStatus($order, $request->validated('status'));

            return $this->successResponse($order, 'Order status updated');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function assign(AssignOrderRequest $request, string $id): JsonResponse
    {
        $order = $this->orderService->getOrderById($id);

        if (! $order) {
            return $this->errorResponse('Order not found', 404);
        }

        $assignedEmployee = Employee::find($request->validated('employee_id'));
        if (! $assignedEmployee) {
            return $this->errorResponse('Employee not found', 404);
        }

        $employee = auth('employee')->user();

        if ($deny = $this->forbidIfOutsideWarehouseScope($order)) {
            return $deny;
        }

        if ($employee && UserRole::isOrderWarehouseScoped($employee->role)) {
            $managedIds = $employee->getManagedWarehouseIds();
            if (empty($managedIds) || ! in_array((string) $assignedEmployee->warehouse_id, $managedIds, true)) {
                return $this->errorResponse('Forbidden. You can only assign orders to staff of your warehouses.', 403);
            }
            // Never re-home the order to another warehouse on assign.
            $order = $this->orderService->assignOrder($order, $request->validated('employee_id'), null);

            return $this->successResponse($order, 'Order assigned');
        }

        if ($employee && $employee->role === \App\Domain\Auth\Enums\UserRole::PublisherManager->value) {
            $pubId = $employee->getManagedPublisherId();
            $wh = \App\Models\Warehouse::find($assignedEmployee->warehouse_id);
            if (! $wh || (string) $wh->publisher_id !== $pubId) {
                return $this->errorResponse('Forbidden. You can only assign orders to staff of your publisher.', 403);
            }
            $order = $this->orderService->assignOrder($order, $request->validated('employee_id'), null);
            return $this->successResponse($order, 'Order assigned');
        }

        $warehouseId = $order->warehouse_id ?: $assignedEmployee->warehouse_id;
        $order = $this->orderService->assignOrder($order, $request->validated('employee_id'), $warehouseId ? (string) $warehouseId : null);

        return $this->successResponse($order, 'Order assigned');
    }

    public function destroy(string $id): JsonResponse
    {
        $order = $this->orderService->getOrderById($id);

        if (! $order) {
            return $this->errorResponse('Order not found', 404);
        }

        if ($deny = $this->forbidIfOutsideWarehouseScope($order)) {
            return $deny;
        }

        $this->orderService->deleteOrder($order);

        return $this->successResponse(null, 'Order deleted');
    }

    public function bulkDestroy(BulkDeleteOrdersRequest $request): JsonResponse
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $request->validated('ids')))));
        $orders = [];
        $forbidden = 0;
        $missing = 0;

        foreach ($ids as $id) {
            $order = $this->orderService->getOrderById($id);
            if (! $order) {
                $missing++;
                continue;
            }
            if ($this->forbidIfOutsideWarehouseScope($order)) {
                $forbidden++;
                continue;
            }
            $orders[] = $order;
        }

        $deleted = $this->orderService->deleteOrders($orders);

        return $this->successResponse([
            'deleted' => $deleted,
            'forbidden' => $forbidden,
            'missing' => $missing,
        ], 'Orders deleted');
    }

    private function forbidIfOutsideWarehouseScope($order): ?JsonResponse
    {
        $employee = auth('employee')->user();
        if (! $employee) {
            return null;
        }

        if ($employee->role === \App\Domain\Auth\Enums\UserRole::PublisherManager->value) {
            $pubId = $employee->getManagedPublisherId();
            if (! $pubId) {
                return $this->errorResponse('Forbidden. No publisher assigned.', 403);
            }
            $orderWarehouseId = $order->warehouse_id ?? $order->employee?->warehouse_id ?? null;
            if ($orderWarehouseId) {
                $wh = \App\Models\Warehouse::find($orderWarehouseId);
                if (! $wh || (string) $wh->publisher_id !== $pubId) {
                    return $this->errorResponse('Forbidden. Order does not belong to your publisher.', 403);
                }
            }
            return null;
        }

        if (! UserRole::isOrderWarehouseScoped($employee->role)) {
            return null; // admin or manager sees everything
        }

        $orderWarehouseId = $order->warehouse_id ?? $order->employee?->warehouse_id ?? null;
        if ($orderWarehouseId === null || ! $employee->managesWarehouse((string) $orderWarehouseId)) {
            return $this->errorResponse('Forbidden. Order does not belong to your warehouses.', 403);
        }

        if ($employee->role === \App\Domain\Auth\Enums\UserRole::Shipping->value) {
            $allowed = [
                \App\Domain\Order\Enums\OrderStatus::ResubmittedToWarehouse->value,
                \App\Domain\Order\Enums\OrderStatus::ProcessingFulfillment->value,
                \App\Domain\Order\Enums\OrderStatus::ShippedCollectingPayment->value,
                \App\Domain\Order\Enums\OrderStatus::Completed->value,
            ];
            if (! in_array($order->status, $allowed, true)) {
                return $this->errorResponse('Forbidden. Shipping can only manage shipping-related orders.', 403);
            }
        }

        return null;
    }
}
