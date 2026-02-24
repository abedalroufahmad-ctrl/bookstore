<?php

namespace App\Http\Controllers\Api;

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
        $perPage = min((int) $request->get('per_page', 15), 100);

        $orders = $this->orderService->getOrdersForEmployee($filters, $perPage);

        return $this->successResponse($orders);
    }

    public function show(string $id): JsonResponse
    {
        $order = $this->orderService->getOrderById($id, null, ['customer']);

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
}
