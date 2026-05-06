<?php

namespace App\Services;

use App\Domain\Cart\Interfaces\CartServiceInterface;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Interfaces\OrderRepositoryInterface;
use App\Domain\Order\Interfaces\OrderServiceInterface;
use App\Domain\Order\Interfaces\StockServiceInterface;
use App\Infrastructure\Repositories\Mongo\PaymentRepository;
use App\Models\Book;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
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
        protected StockServiceInterface $stockService,
        protected PaymentRepository $paymentRepository
    ) {}

    public function checkout($user, array $shippingAddress, string $paymentMethod, ?array $paymentInfo = null): array
    {
        $cart = $this->cartService->getOrCreateActiveCart($user);

        if (empty($cart->items)) {
            throw new \InvalidArgumentException('Cart is empty.');
        }

        $paymentStatus = $paymentMethod === 'cod' ? Payment::statusPaid() : Payment::statusPending();

        $doCheckout = function () use ($cart, $user, $shippingAddress, $paymentInfo, $paymentMethod, $paymentStatus) {
            $groupedByWarehouse = $this->groupCartItemsByWarehouse($cart->items);
            $createdOrders = [];

            foreach ($groupedByWarehouse as $warehouseId => $items) {
                $this->stockService->validateAndDeduct($items);
                $orderTotal = $this->calculateItemsTotal($items);

                $order = $this->orderRepository->create([
                    'customer_id' => $user->getKey(),
                    'warehouse_id' => $warehouseId,
                    'items' => $items,
                    'status' => OrderStatus::PendingReview->value,
                    'total' => $orderTotal,
                    'shipping_address' => $shippingAddress,
                    'payment_info' => $paymentInfo ?? [],
                    'payment_method' => $paymentMethod,
                    'payment_status' => $paymentStatus,
                ]);

                $this->paymentRepository->create([
                    'order_id' => $order->getKey(),
                    'user_id' => $user->getKey(),
                    'payment_method' => $paymentMethod,
                    'payment_status' => $paymentStatus,
                    'transaction_id' => null,
                ]);

                $createdOrders[] = $order->fresh();
            }

            $this->cartService->markAsConverted($cart);

            return $createdOrders;
        };

        if (config('database.mongodb_transactions_enabled', false)) {
            return DB::connection('mongodb')->transaction($doCheckout);
        }

        return $doCheckout();
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function groupCartItemsByWarehouse(array $items): array
    {
        $grouped = [];

        foreach ($items as $item) {
            $bookId = $item['book_id'] ?? null;
            if (! $bookId) {
                continue;
            }

            $book = Book::find($bookId);
            if (! $book) {
                throw new \InvalidArgumentException("Book not found: {$bookId}");
            }

            $warehouseId = (string) ($book->warehouse_id ?? '');
            if ($warehouseId === '') {
                throw new \InvalidArgumentException("Book '{$book->title}' is not assigned to a warehouse.");
            }

            $grouped[$warehouseId][] = $item;
        }

        if ($grouped === []) {
            throw new \InvalidArgumentException('Cart has no valid items.');
        }

        return $grouped;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function calculateItemsTotal(array $items): float
    {
        $total = 0.0;
        foreach ($items as $item) {
            $price = (float) ($item['price'] ?? 0);
            $quantity = max(0, (int) ($item['quantity'] ?? 0));
            $total += $price * $quantity;
        }

        return $total;
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

    public function assignOrder(Order $order, string $employeeId, ?string $warehouseId = null): Order
    {
        $data = ['employee_id' => $employeeId];
        if ($warehouseId !== null) {
            $data['warehouse_id'] = $warehouseId;
        }
        $this->orderRepository->update($order->getKey(), $data);

        return $order->fresh(['customer', 'employee']);
    }

    public function getOrdersForCustomer($user, int $perPage = 15): LengthAwarePaginator
    {
        return $this->orderRepository->getByCustomerId($user->getKey(), $perPage);
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

    /**
     * Mark payment and order as paid (e.g. after successful gateway callback/webhook).
     */
    public function markOrderPaymentPaid(string $orderId, ?string $transactionId = null): void
    {
        $payment = $this->paymentRepository->findByOrderId($orderId);
        if ($payment) {
            $this->paymentRepository->updateStatus($payment->getKey(), Payment::statusPaid(), $transactionId);
        }
        $this->orderRepository->update($orderId, [
            'payment_status' => Payment::statusPaid(),
        ]);
    }
}
