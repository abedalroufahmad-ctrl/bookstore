<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Publisher;
use App\Models\PublisherCommissionEntry;
use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PublisherCommissionAccountService
{
    /**
     * Accrue platform commission when a sale is finalized (completed / POS).
     * Idempotent per order so retries and duplicate hooks do not double-count.
     */
    public function accrueForOrder(Order $order): ?PublisherCommissionEntry
    {
        $orderId = (string) $order->getKey();
        if ($orderId === '') {
            return null;
        }

        $existing = PublisherCommissionEntry::query()
            ->where('order_id', $orderId)
            ->where('type', PublisherCommissionEntry::TYPE_SALE_ACCRUAL)
            ->first();
        if ($existing) {
            return $existing;
        }

        $amount = round((float) ($order->platform_commission_amount ?? 0), 2);
        if ($amount <= 0) {
            return null;
        }

        $publisherId = $this->resolvePublisherId($order);
        if ($publisherId === null) {
            return null;
        }

        try {
            $entry = PublisherCommissionEntry::create([
                'publisher_id' => $publisherId,
                'order_id' => $orderId,
                'type' => PublisherCommissionEntry::TYPE_SALE_ACCRUAL,
                'amount' => $amount,
                'note' => null,
                'reference' => null,
                'created_by' => null,
            ]);
        } catch (\Throwable $e) {
            // Concurrent duplicate insert (unique order_id) — return the winner.
            $existing = PublisherCommissionEntry::query()
                ->where('order_id', $orderId)
                ->where('type', PublisherCommissionEntry::TYPE_SALE_ACCRUAL)
                ->first();
            if ($existing) {
                return $existing;
            }
            throw $e;
        }

        $this->adjustPublisherBalances($publisherId, owedDelta: $amount, accruedDelta: $amount, paidDelta: 0);

        return $entry;
    }

    /**
     * Record that the publishing house transferred commission to the site account.
     *
     * @throws \InvalidArgumentException
     */
    public function recordSettlement(
        string $publisherId,
        float $amount,
        ?string $employeeId = null,
        ?string $note = null,
        ?string $reference = null
    ): PublisherCommissionEntry {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Settlement amount must be greater than zero.');
        }

        $publisher = Publisher::query()->find($publisherId);
        if (! $publisher) {
            throw new \InvalidArgumentException('Publisher not found.');
        }

        $owed = round((float) ($publisher->commission_owed ?? 0), 2);
        if ($amount > $owed) {
            throw new \InvalidArgumentException(
                sprintf('Settlement amount (%.2f) exceeds commission owed (%.2f).', $amount, $owed)
            );
        }

        $entry = PublisherCommissionEntry::create([
            'publisher_id' => $publisherId,
            'order_id' => null,
            'type' => PublisherCommissionEntry::TYPE_SETTLEMENT,
            'amount' => $amount,
            'note' => $this->nullableTrim($note),
            'reference' => $this->nullableTrim($reference),
            'created_by' => $employeeId,
        ]);

        $this->adjustPublisherBalances($publisherId, owedDelta: -$amount, accruedDelta: 0, paidDelta: $amount);

        return $entry;
    }

    /**
     * @return array{
     *     publisher_id: string,
     *     commission_owed: float,
     *     commission_accrued_total: float,
     *     commission_paid_total: float,
     *     platform_commission_percent: float
     * }
     */
    public function getAccountSummary(string $publisherId): array
    {
        $publisher = Publisher::query()->find($publisherId);
        if (! $publisher) {
            throw new \InvalidArgumentException('Publisher not found.');
        }

        $settings = is_array($publisher->settings) ? $publisher->settings : [];

        return [
            'publisher_id' => $publisherId,
            'commission_owed' => round((float) ($publisher->commission_owed ?? 0), 2),
            'commission_accrued_total' => round((float) ($publisher->commission_accrued_total ?? 0), 2),
            'commission_paid_total' => round((float) ($publisher->commission_paid_total ?? 0), 2),
            'platform_commission_percent' => round((float) ($settings['platform_commission_percent'] ?? 0), 2),
        ];
    }

    public function getLedger(string $publisherId, int $perPage = 24): LengthAwarePaginator
    {
        return PublisherCommissionEntry::query()
            ->where('publisher_id', $publisherId)
            ->orderByDesc('created_at')
            ->paginate(min(max($perPage, 1), 100));
    }

    private function resolvePublisherId(Order $order): ?string
    {
        $warehouseId = trim((string) ($order->warehouse_id ?? ''));
        if ($warehouseId === '') {
            return null;
        }

        $warehouse = Warehouse::query()->find($warehouseId);
        $publisherId = trim((string) ($warehouse?->publisher_id ?? ''));

        return $publisherId === '' ? null : $publisherId;
    }

    private function adjustPublisherBalances(
        string $publisherId,
        float $owedDelta,
        float $accruedDelta,
        float $paidDelta
    ): void {
        $publisher = Publisher::query()->find($publisherId);
        if (! $publisher) {
            return;
        }

        $publisher->commission_owed = round(max(0, (float) ($publisher->commission_owed ?? 0) + $owedDelta), 2);
        $publisher->commission_accrued_total = round(
            max(0, (float) ($publisher->commission_accrued_total ?? 0) + $accruedDelta),
            2
        );
        $publisher->commission_paid_total = round(
            max(0, (float) ($publisher->commission_paid_total ?? 0) + $paidDelta),
            2
        );
        $publisher->save();
    }

    private function nullableTrim(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
