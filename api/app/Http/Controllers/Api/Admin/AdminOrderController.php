<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\AssignOrderRequest;
use App\Http\Requests\Order\UpdateOrderStatusRequest;
use App\Domain\Order\Interfaces\OrderServiceInterface;
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
            'employee_id' => $request->get('employee_id'),
            'unassigned' => $request->boolean('unassigned'),
        ];
        $perPage = min((int) $request->get('per_page', 15), 100);

        $orders = $this->orderService->getOrdersForAdmin($filters, $perPage);

        return $this->successResponse($orders);
    }

    public function show(string $id): JsonResponse
    {
        $order = $this->orderService->getOrderById($id, null, ['customer', 'employee']);

        if (! $order) {
            return $this->errorResponse('Order not found', 404);
        }

        return $this->successResponse($order);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, string $id): JsonResponse
    {
        try {
            $order = $this->orderService->getOrderById($id);

            if (! $order) {
                return $this->errorResponse('Order not found', 404);
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

        $order = $this->orderService->assignOrder($order, $request->validated('employee_id'));

        return $this->successResponse($order, 'Order assigned');
    }
}
