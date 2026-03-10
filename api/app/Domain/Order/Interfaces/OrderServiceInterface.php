<?php

namespace App\Domain\Order\Interfaces;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OrderServiceInterface
{
    public function checkout(Customer $customer, array $shippingAddress, string $paymentMethod, ?array $paymentInfo = null): Order;

    public function updateStatus(Order $order, string $newStatus): Order;

    public function assignOrder(Order $order, string $employeeId, ?string $warehouseId = null): Order;

    public function getOrdersForCustomer(Customer $customer, int $perPage = 15): LengthAwarePaginator;

    public function getOrderById(string $id, ?string $customerId = null, array $with = []): ?Order;

    public function getOrdersForAdmin(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function getOrdersForEmployee(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function markOrderPaymentPaid(string $orderId, ?string $transactionId = null): void;
}
