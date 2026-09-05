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
use App\Models\Setting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderService extends BaseService implements OrderServiceInterface
{
    public const VALID_STATUSES = [
        OrderStatus::PendingWarehouseReview->value,
        OrderStatus::AwaitingCustomerConfirmation->value,
        OrderStatus::ResubmittedToWarehouse->value,
        OrderStatus::ProcessingFulfillment->value,
        OrderStatus::ShippedCollectingPayment->value,
        OrderStatus::Completed->value,
        OrderStatus::Cancelled->value,
    ];

    public function __construct(
        protected CartServiceInterface $cartService,
        protected OrderRepositoryInterface $orderRepository,
        protected StockServiceInterface $stockService,
        protected PaymentRepository $paymentRepository,
        protected PublisherPayoutService $publisherPayoutService
    ) {}

    public function createPosInvoice(array $items, string $warehouseId, ?string $employeeId = null, ?string $customerName = null): Order
    {
        if (empty($items)) {
            throw new \InvalidArgumentException('Invoice items cannot be empty.');
        }

        $doCreate = function () use ($items, $warehouseId, $employeeId, $customerName) {
            $repricedItems = $this->cartService->repriceItems($items);
            if ($repricedItems === []) {
                throw new \InvalidArgumentException('Some items are invalid or unavailable.');
            }

            // Ensure all items are from the same warehouse
            $grouped = $this->groupCartItemsByWarehouse($repricedItems);
            if (count($grouped) > 1 || ! isset($grouped[$warehouseId])) {
                throw new \InvalidArgumentException('All books must belong to the selected warehouse.');
            }

            $this->stockService->validateAndDeduct($repricedItems);
            $booksSubtotal = $this->calculateItemsTotal($repricedItems);
            $shippingFee = $this->invoiceShippingFee();
            $total = round($booksSubtotal + $shippingFee, 2);
            $payout = $this->publisherPayoutService->snapshotForWarehouse($warehouseId, $booksSubtotal);

            $order = null;
            try {
                $order = $this->orderRepository->create(array_merge([
                    'employee_id' => $employeeId,
                    'customer_name' => $customerName,
                    'is_direct_sale' => true,
                    'warehouse_id' => $warehouseId,
                    'items' => $repricedItems,
                    'status' => OrderStatus::Completed->value,
                    'books_subtotal' => $booksSubtotal,
                    'shipping_fee' => $shippingFee,
                    'shipping_method' => 'pos',
                    'total' => $total,
                    'shipping_address' => [],
                    'payment_info' => [],
                    'payment_method' => 'cash',
                    'payment_status' => Payment::statusPaid(),
                ], $payout));

                $this->paymentRepository->create([
                    'order_id' => $order->getKey(),
                    'user_id' => $employeeId,
                    'amount' => $total,
                    'currency' => 'USD',
                    'method' => 'cash',
                    'status' => Payment::statusPaid(),
                    'transaction_id' => 'POS-'.$order->getKey().'-'.uniqid('', true),
                ]);
            } catch (\Throwable $e) {
                $this->stockService->restore($repricedItems);
                if ($order) {
                    $order->delete();
                }
                throw $e;
            }

            if (! $order instanceof Order) {
                throw new \RuntimeException('Failed to create invoice.');
            }

            $this->enrichOrderItemsWithBookTitles($order);
            $order->loadMissing(['warehouse.publisher', 'employee']);

            return $order;
        };

        // Standalone MongoDB cannot run multi-document transactions (needs replica set / mongos).
        if (config('database.mongodb_transactions_enabled', false)) {
            return DB::connection('mongodb')->transaction($doCreate);
        }

        return $doCreate();
    }

    public function checkout(Customer $customer, array $shippingAddress, string $paymentMethod, ?array $paymentInfo = null): array
    {
        $cart = $this->cartService->getOrCreateActiveCart($customer);

        if (empty($cart->items)) {
            throw new \InvalidArgumentException('Cart is empty.');
        }

        $paymentStatusPending = Payment::statusPending();

        $doCheckout = function () use ($cart, $customer, $shippingAddress, $paymentInfo, $paymentMethod, $paymentStatusPending) {
            $repricedItems = $this->cartService->repriceItems($cart->items ?? []);
            if ($repricedItems === []) {
                throw new \InvalidArgumentException('Cart is empty.');
            }

            $groupedByWarehouse = $this->groupCartItemsByWarehouse($repricedItems);
            $createdOrders = [];

            foreach ($groupedByWarehouse as $warehouseId => $items) {
                $this->stockService->validateAvailability($items);
                $booksSubtotal = $this->calculateItemsTotal($items);
                $shippingFee = $this->invoiceShippingFee();
                $total = round($booksSubtotal + $shippingFee, 2);
                $payout = $this->publisherPayoutService->snapshotForWarehouse((string) $warehouseId, $booksSubtotal);

                $order = $this->orderRepository->create(array_merge([
                    'customer_id' => $customer->getKey(),
                    'warehouse_id' => $warehouseId,
                    'items' => $items,
                    'status' => OrderStatus::PendingWarehouseReview->value,
                    'books_subtotal' => $booksSubtotal,
                    'shipping_fee' => $shippingFee,
                    'shipping_method' => null,
                    'total' => $total,
                    'shipping_address' => $shippingAddress,
                    'payment_info' => $paymentInfo ?? [],
                    'payment_method' => $paymentMethod,
                    'payment_status' => $paymentStatusPending,
                ], $payout));

                $this->paymentRepository->create([
                    'order_id' => $order->getKey(),
                    'user_id' => $customer->getKey(),
                    'payment_method' => $paymentMethod,
                    'payment_status' => $paymentStatusPending,
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

    public function submitWarehouseQuote(Order $order, array $data): Order
    {
        $current = OrderStatus::normalizeStored((string) $order->status);
        if ($current !== OrderStatus::PendingWarehouseReview) {
            throw new \InvalidArgumentException('Order must be awaiting warehouse pricing before submitting a quote.');
        }

        $shippingFee = (float) ($data['shipping_fee'] ?? 0);
        if ($shippingFee < 0) {
            throw new \InvalidArgumentException('shipping_fee cannot be negative.');
        }

        $booksSubtotal = (float) ($order->books_subtotal ?? $this->calculateItemsTotal($order->items ?? []));
        $total = round($booksSubtotal + $shippingFee, 2);
        $payout = $this->publisherPayoutService->snapshotForOrder($order, $booksSubtotal);

        $update = array_merge([
            'books_subtotal' => $booksSubtotal,
            'shipping_fee' => $shippingFee,
            'shipping_method' => isset($data['shipping_method']) ? (string) $data['shipping_method'] : ($order->shipping_method ?? null),
            'total' => $total,
            'status' => OrderStatus::AwaitingCustomerConfirmation->value,
        ], $payout);

        if (! empty($data['payment_method']) && is_string($data['payment_method'])) {
            $update['payment_method'] = $data['payment_method'];
            $payment = $this->paymentRepository->findByOrderId($order->getKey());
            if ($payment) {
                $payment->update(['payment_method' => $data['payment_method']]);
            }
        }

        $this->orderRepository->update($order->getKey(), $update);

        return $order->fresh(['customer', 'employee']);
    }

    /**
     * Customer accepts non-prepaid warehouse quote (e.g. COD) and sends order back for fulfillment scheduling.
     */
    public function confirmOrderQuoteByCustomer(Order $order, Customer $customer): Order
    {
        if ((string) $order->customer_id !== (string) $customer->getKey()) {
            throw new \InvalidArgumentException('Order does not belong to this customer.');
        }

        $current = OrderStatus::normalizeStored((string) $order->status);
        if ($current !== OrderStatus::AwaitingCustomerConfirmation) {
            throw new \InvalidArgumentException('Order is not waiting for customer confirmation.');
        }

        if ((string) ($order->payment_method ?? '') === 'paypal') {
            throw new \InvalidArgumentException('Complete PayPal payment to confirm PayPal orders.');
        }

        $this->orderRepository->update($order->getKey(), [
            'status' => OrderStatus::ResubmittedToWarehouse->value,
        ]);

        return $order->fresh(['customer', 'employee']);
    }

    public function updateStatus(Order $order, string $newStatus): Order
    {
        if (! in_array($newStatus, self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException("Invalid status: {$newStatus}");
        }

        $current = OrderStatus::normalizeStored((string) $order->status)
            ?? throw new \InvalidArgumentException('Unsupported or unknown order status.');

        $next = OrderStatus::tryFrom($newStatus);
        if ($next === null) {
            throw new \InvalidArgumentException("Invalid status: {$newStatus}");
        }

        if ($next === OrderStatus::Cancelled) {
            if (! $current->canBeCancelled()) {
                throw new \InvalidArgumentException('Order cannot be cancelled from its current status.');
            }

            if ($current === OrderStatus::ProcessingFulfillment) {
                $this->stockService->restore($order->items ?? []);
            }

            $this->orderRepository->update($order->getKey(), ['status' => OrderStatus::Cancelled->value]);

            return $order->fresh();
        }

        $this->assertEmployeeWorkflowTransition($current, $next);

        if ($current === OrderStatus::ResubmittedToWarehouse && $next === OrderStatus::ProcessingFulfillment) {
            $this->stockService->validateAndDeduct($order->items ?? []);
        }

        $patch = ['status' => $next->value];

        if ($next === OrderStatus::Completed
            && (string) ($order->payment_method ?? '') === 'cod'
            && (string) ($order->payment_status ?? '') === Payment::statusPending()) {
            $this->markOrderPaymentPaid($order->getKey(), null);
        }

        $this->orderRepository->update($order->getKey(), $patch);

        return $order->fresh(['customer', 'employee']);
    }

    private function assertEmployeeWorkflowTransition(OrderStatus $current, OrderStatus $next): void
    {
        $allowed = match ($current) {
            OrderStatus::PendingWarehouseReview => [
                OrderStatus::Cancelled,
            ],
            OrderStatus::AwaitingCustomerConfirmation => [
                OrderStatus::Cancelled,
            ],
            OrderStatus::ResubmittedToWarehouse => [
                OrderStatus::ProcessingFulfillment,
                OrderStatus::Cancelled,
            ],
            OrderStatus::ProcessingFulfillment => [
                OrderStatus::ShippedCollectingPayment,
                OrderStatus::Cancelled,
            ],
            OrderStatus::ShippedCollectingPayment => [
                OrderStatus::Completed,
            ],
            default => [],
        };

        if (! in_array($next, $allowed, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Cannot move order from "%s" to "%s".',
                $current->value,
                $next->value
            ));
        }
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

        return round($total, 2);
    }

    /** Global shipping charge applied to every new POS invoice / online order. */
    private function invoiceShippingFee(): float
    {
        $raw = Setting::get('invoice_shipping_fee', 0);
        $fee = is_numeric($raw) ? (float) $raw : 0.0;

        return max(0.0, round($fee, 2));
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

    public function getOrdersForCustomer(Customer $customer, int $perPage = 24): LengthAwarePaginator
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

        $this->enrichOrderItemsWithBookTitles($order);

        return $order;
    }

    /**
     * Add book_title / weight to each line item for API consumers.
     * Persisted weight is kept; missing weight is filled from the current book record.
     */
    private function enrichOrderItemsWithBookTitles(Order $order): void
    {
        $items = $order->items;
        if (! is_array($items) || $items === []) {
            return;
        }

        $ids = [];
        foreach ($items as $row) {
            if (is_array($row) && ! empty($row['book_id'])) {
                $ids[] = (string) $row['book_id'];
            }
        }

        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return;
        }

        $books = Book::query()->findMany($ids);

        $byId = [];
        foreach ($books as $book) {
            $byId[(string) $book->getKey()] = $book;
        }

        foreach ($items as $i => $row) {
            if (! is_array($row)) {
                continue;
            }

            $bid = isset($row['book_id']) ? (string) $row['book_id'] : '';
            if ($bid === '' || ! isset($byId[$bid])) {
                continue;
            }

            $book = $byId[$bid];
            $title = $this->normalizeBookTitleForOrderItem($book->title ?? null);
            if ($title !== '') {
                $items[$i]['book_title'] = $title;
            }
            if (! array_key_exists('weight', $row) || $row['weight'] === null || $row['weight'] === '') {
                $items[$i]['weight'] = $book->weight !== null && is_numeric($book->weight)
                    ? round((float) $book->weight, 3)
                    : null;
            }
        }

        $order->setAttribute('items', $items);
    }

    private function normalizeBookTitleForOrderItem(mixed $title): string
    {
        if (is_string($title)) {
            return trim($title);
        }

        if (is_array($title)) {
            foreach (['en', 'ar'] as $k) {
                if (isset($title[$k]) && is_string($title[$k]) && trim($title[$k]) !== '') {
                    return trim($title[$k]);
                }
            }

            foreach ($title as $v) {
                if (is_string($v) && trim($v) !== '') {
                    return trim($v);
                }
            }
        }

        return '';
    }

    public function getOrdersForAdmin(array $filters = [], int $perPage = 24): LengthAwarePaginator
    {
        $filters['with'] = ['customer', 'employee'];

        return $this->orderRepository->getPaginated($filters, $perPage);
    }

    public function getOrdersForEmployee(array $filters = [], int $perPage = 24): LengthAwarePaginator
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

        $order = $this->orderRepository->findById($orderId);
        $patch = [
            'payment_status' => Payment::statusPaid(),
        ];

        if ($order !== null && OrderStatus::normalizeStored((string) $order->status) === OrderStatus::AwaitingCustomerConfirmation
            && (string) ($order->payment_method ?? '') === 'paypal') {
            $patch['status'] = OrderStatus::ResubmittedToWarehouse->value;
        }

        $this->orderRepository->update($orderId, $patch);
    }

    public function deleteOrder(Order $order): bool
    {
        $this->restoreStockIfNeededBeforeDelete($order);

        return $this->orderRepository->delete((string) $order->getKey());
    }

    public function deleteOrders(array $orders): int
    {
        $ids = [];
        foreach ($orders as $order) {
            if (! $order instanceof Order) {
                continue;
            }
            $this->restoreStockIfNeededBeforeDelete($order);
            $ids[] = (string) $order->getKey();
        }

        if ($ids === []) {
            return 0;
        }

        return $this->orderRepository->deleteMany($ids);
    }

    /**
     * Stock is deducted when entering processing_fulfillment. Restore on delete
     * for in-progress fulfillment / shipping (not completed sales).
     */
    private function restoreStockIfNeededBeforeDelete(Order $order): void
    {
        $status = OrderStatus::normalizeStored((string) $order->status);
        if (
            $status === OrderStatus::ProcessingFulfillment
            || $status === OrderStatus::ShippedCollectingPayment
        ) {
            $this->stockService->restore($order->items ?? []);
        }
    }

    public function markPayPalOrdersPaid(array $orderIds, ?string $transactionId): void
    {
        foreach ($orderIds as $id) {
            $id = trim((string) $id);
            if ($id === '') {
                continue;
            }

            $order = $this->orderRepository->findById($id);
            if (! $order) {
                throw new \InvalidArgumentException("Order not found: {$id}");
            }

            if ((string) $order->payment_method !== 'paypal') {
                throw new \InvalidArgumentException('Order is not configured for PayPal.');
            }

            $payStatus = (string) $order->payment_status;
            if ($payStatus === Payment::statusPaid()) {
                continue;
            }

            if ($payStatus !== Payment::statusPending()) {
                throw new \InvalidArgumentException("Order {$id} is not awaiting payment.");
            }

            $workflow = OrderStatus::normalizeStored((string) $order->status);

            if ($workflow === OrderStatus::AwaitingCustomerConfirmation) {
                $this->markOrderPaymentPaid($id, $transactionId);

                continue;
            }

            if ($workflow === OrderStatus::ResubmittedToWarehouse) {
                $payment = $this->paymentRepository->findByOrderId($id);
                if ($payment) {
                    $this->paymentRepository->updateStatus($payment->getKey(), Payment::statusPaid(), $transactionId);
                }
                $this->orderRepository->update($id, ['payment_status' => Payment::statusPaid()]);

                continue;
            }

            throw new \InvalidArgumentException("Order {$id} cannot accept PayPal capture in status {$order->status}.");
        }
    }
}
