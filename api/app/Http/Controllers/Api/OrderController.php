<?php

namespace App\Http\Controllers\Api;

use App\Domain\Order\Interfaces\OrderServiceInterface;
use App\Http\Requests\Order\CheckoutRequest;
use App\Http\Requests\Order\UpdateOrderStatusRequest;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class OrderController extends BaseApiController
{
    public function __construct(
        private OrderServiceInterface $orderService
    ) {}

    public function checkout(CheckoutRequest $request): JsonResponse
    {
        try {
            $customer = auth('customer')->user();
            $order = $this->orderService->checkout(
                $customer,
                $request->validated('shipping_address'),
                $request->validated('payment_info')
            );

            return $this->successResponse($order, 'Order created successfully', 201);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->errorResponse(
                config('app.debug') ? $e->getMessage() : 'Checkout failed. Please try again.',
                500
            );
        }
    }

    public function index(): JsonResponse
    {
        $customer = auth('customer')->user();
        $orders = $this->orderService->getOrdersForCustomer($customer, 15);

        return $this->successResponse($orders);
    }

    public function show(string $id): JsonResponse
    {
        $customer = auth('customer')->user();
        $order = $this->orderService->getOrderById($id, $customer->getKey());

        if (! $order) {
            return $this->errorResponse('Order not found', 404);
        }

        return $this->successResponse($order);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, string $id): JsonResponse
    {
        try {
            $customer = auth('customer')->user();
            $order = $this->orderService->getOrderById($id, $customer->getKey());

            if (! $order) {
                return $this->errorResponse('Order not found', 404);
            }

            $newStatus = $request->validated('status');

            if ($newStatus !== OrderService::STATUS_CANCELLED) {
                return $this->errorResponse('Customers can only cancel orders. Use employee API for other status updates.', 403);
            }

            $order = $this->orderService->updateStatus($order, $newStatus);

            return $this->successResponse($order, 'Order cancelled');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }
}
