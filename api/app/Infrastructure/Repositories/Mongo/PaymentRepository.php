<?php

namespace App\Infrastructure\Repositories\Mongo;

use App\Models\Payment;

class PaymentRepository
{
    public function __construct(
        protected Payment $model
    ) {}

    public function create(array $data): Payment
    {
        return $this->model->create($data);
    }

    public function findByOrderId(string $orderId): ?Payment
    {
        return $this->model->newQuery()->where('order_id', $orderId)->first();
    }

    public function updateStatus(string $id, string $status, ?string $transactionId = null): bool
    {
        $payment = $this->model->newQuery()->find($id);
        if (! $payment) {
            return false;
        }
        $update = ['payment_status' => $status];
        if ($transactionId !== null) {
            $update['transaction_id'] = $transactionId;
        }
        return $payment->update($update);
    }

    public function findById(string $id): ?Payment
    {
        return $this->model->newQuery()->find($id);
    }
}
