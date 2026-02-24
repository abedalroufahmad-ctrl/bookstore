<?php

namespace App\Services;

use App\Domain\Cart\Interfaces\CartServiceInterface;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Interfaces\OrderRepositoryInterface;
use App\Domain\Order\Interfaces\OrderServiceInterface;
use App\Domain\Order\Interfaces\StockServiceInterface;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderService extends BaseService implements OrderServiceInterface
{
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PREPARING = 'preparing';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    public const VALID_STATUSES = [
        self::STATUS_PENDING_REVIEW,
        self::STATUS_CONFIRMED,
        self::STATUS_PREPARING,
        self::STATUS_SHIPPED,
        self::STATUS_DELIVERED,
        self::STATUS_CANCELLED,
    ];

    public function __construct(
        protected CartServiceInterface $cartService,
        protected OrderRepositoryInterface $orderRepository,
        protected StockServiceInterface $stockService
    ) {}

    public function checkout(Customer $customer, array $shippingAddress, ?array $paymentInfo = null): Order
    {
        $cart = $this->cartService->getOrCreateActiveCart($customer);

        if (empty($cart->items)) {
            throw new \InvalidArgumentException('Cart is empty.');
        }

        $total = $this->cartService->calculateTotal($cart);

        $doCheckout = function () use ($cart, $customer, $shippingAddress, $paymentInfo, $total) {
            $this->stockService->validateAndDeduct($cart->items);

            $order = $this->orderRepository->create([
                'customer_id' => $customer->getKey(),
                'items' => $cart->items,
                'status' => OrderStatus::PendingReview->value,
                'total' => $total,
                'shipping_address' => $shippingAddress,
                'payment_info' => $paymentInfo,
            ]);

            $this->cartService->markAsConverted($cart);

            return $order->fresh();
        };

        if (config('database.mongodb_transactions_enabled', false)) {
            return DB::connection('mongodb')->transaction($doCheckout);
        }

        return $doCheckout();
    }

    public function updateStatus(Order $order, string $newStatus): Order
    {
        if (! in_array($newStatus, self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException("Invalid status: {$newStatus}");
        }

        $currentStatus = $order->status;

        if ($newStatus === self::STATUS_CANCELLED && $currentStatus !== self::STATUS_CANCELLED) {
            $this->stockService->restore($order->items);
        }

        $this->orderRepository->update($order->getKey(), ['status' => $newStatus]);

        return $order->fresh();
    }

    public function assignOrder(Order $order, string $employeeId): Order
    {
        $this->orderRepository->update($order->getKey(), ['employee_id' => $employeeId]);

        return $order->fresh(['customer', 'employee']);
    }

    public function getOrdersForCustomer(Customer $customer, int $perPage = 15): LengthAwarePaginator
    {
        return $this->orderRepository->getByCustomerId($customer->getKey(), $perPage);
    }

    public function getOrderById(string $id, ?string $customerId = null, array $with = []): ?Order
    {
        $order = $this->orderRepository->findById($id, $with);

        if (! $order) {
            return null;
        }

        if ($customerId !== null && (string) $order->customer_id !== (string) $customerId) {
            return null;
        }

        return $order;
    }

    public function getOrdersForAdmin(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $filters['with'] = ['customer', 'employee'];

        return $this->orderRepository->getPaginated($filters, $perPage);
    }

    public function getOrdersForEmployee(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $filters['with'] = ['customer'];

        return $this->orderRepository->getPaginated($filters, $perPage);
    }
}
