<?php

namespace App\Domain\Order\Interfaces;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OrderServiceInterface
{
    /**
     * Create one or more orders from the active cart.
     * Items are split by warehouse, each warehouse gets an independent order.
     *
     * @return array<int, Order>
     */
    public function checkout(Customer $customer, array $shippingAddress, string $paymentMethod, ?array $paymentInfo = null): array;

    /**
     * Warehouse sets confirmed books subtotal (existing), shipping fee, and confirms shipping/payment preferences.
     */
    public function submitWarehouseQuote(Order $order, array $data): Order;

    /**
     * Customer accepts quoted order (COD, etc.). PayPal orders use paypal/start instead.
     */
    public function confirmOrderQuoteByCustomer(Order $order, Customer $customer): Order;

    public function updateStatus(Order $order, string $newStatus): Order;

    public function assignOrder(Order $order, string $employeeId, ?string $warehouseId = null): Order;

    public function getOrdersForCustomer(Customer $customer, int $perPage = 15): LengthAwarePaginator;

    public function getOrderById(string $id, ?string $customerId = null, array $with = []): ?Order;

    public function getOrdersForAdmin(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function getOrdersForEmployee(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Permanently delete an order. Restores stock when it was deducted and not yet fulfilled.
     */
    public function deleteOrder(Order $order): bool;

    /**
     * @param  array<int, Order>  $orders
     * @return int Number of deleted orders
     */
    public function deleteOrders(array $orders): int;

    public function markOrderPaymentPaid(string $orderId, ?string $transactionId = null): void;

    /**
     * Mark several orders paid after PayPal capture (custom_id lists our order IDs).
     *
     * @param  array<int, string>  $orderIds
     */
    public function markPayPalOrdersPaid(array $orderIds, ?string $transactionId): void;
}
