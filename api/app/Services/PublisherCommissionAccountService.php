<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Publisher;
use App\Models\PublisherCommissionEntry;
use App\Models\Warehouse;
use App\Support\CurrencyAmount;
use Carbon\CarbonImmutable;
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

        $currencyCode = strtoupper((string) ($order->currency_code ?? 'USD'));
        $amount = CurrencyAmount::round((float) ($order->platform_commission_amount ?? 0), $currencyCode);
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
                'currency_code' => $currencyCode,
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

        $this->adjustPublisherBalances($publisherId, $currencyCode, owedDelta: $amount, accruedDelta: $amount, paidDelta: 0);

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
        ?string $reference = null,
        string $currencyCode = 'USD'
    ): PublisherCommissionEntry {
        $currencyCode = strtoupper($currencyCode);
        $amount = CurrencyAmount::round($amount, $currencyCode);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Settlement amount must be greater than zero.');
        }

        $publisher = Publisher::query()->find($publisherId);
        if (! $publisher) {
            throw new \InvalidArgumentException('Publisher not found.');
        }

        $balances = $this->publisherBalances($publisher);
        $owed = CurrencyAmount::round((float) ($balances[$currencyCode]['commission_owed'] ?? 0), $currencyCode);
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
            'currency_code' => $currencyCode,
            'note' => $this->nullableTrim($note),
            'reference' => $this->nullableTrim($reference),
            'created_by' => $employeeId,
        ]);

        $this->adjustPublisherBalances($publisherId, $currencyCode, owedDelta: -$amount, accruedDelta: 0, paidDelta: $amount);

        return $entry;
    }

    /**
     * @return array{
     *     publisher_id: string,
     *     commission_owed: float,
     *     commission_accrued_total: float,
     *     commission_paid_total: float,
     *     platform_commission_percent: float,
     *     currency_balances: array<string, array{commission_owed: float, commission_accrued_total: float, commission_paid_total: float}>
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
            'commission_owed' => CurrencyAmount::round((float) ($publisher->commission_owed ?? 0), 'USD'),
            'commission_accrued_total' => CurrencyAmount::round((float) ($publisher->commission_accrued_total ?? 0), 'USD'),
            'commission_paid_total' => CurrencyAmount::round((float) ($publisher->commission_paid_total ?? 0), 'USD'),
            'platform_commission_percent' => round((float) ($settings['platform_commission_percent'] ?? 0), 2),
            'currency_balances' => $this->publisherBalances($publisher),
        ];
    }

    public function getLedger(string $publisherId, int $perPage = 24): LengthAwarePaginator
    {
        return PublisherCommissionEntry::query()
            ->where('publisher_id', $publisherId)
            ->orderByDesc('created_at')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @return array{orders: LengthAwarePaginator, total_sum: float|null, totals_by_currency: array<string, float>}
     */
    public function getOrderTotals(
        string $publisherId,
        ?CarbonImmutable $start,
        ?CarbonImmutable $end,
        int $perPage = 25
    ): array {
        $warehouseIds = Warehouse::query()
            ->where('publisher_id', $publisherId)
            ->pluck('_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $query = Order::query()
            ->whereIn('warehouse_id', $warehouseIds)
            ->orderByDesc('created_at');
        if ($start !== null && $end !== null) {
            $query->whereBetween('created_at', [$start, $end]);
        }

        $publisher = Publisher::query()->find($publisherId);
        $currencyCodes = $this->queryCurrencyCodes($query, $publisher ? [$publisher] : []);
        $totals = $this->sumByCurrency($query, $currencyCodes, 'total');
        $orders = $query->paginate(min(max($perPage, 1), 100), ['_id', 'total', 'currency_code', 'created_at']);
        $orders->through(fn (Order $order) => [
            'total' => (float) $order->total,
            'currency_code' => strtoupper((string) ($order->currency_code ?? 'USD')),
            'created_at' => $order->created_at,
        ]);

        return [
            'orders' => $orders,
            'total_sum' => count($totals) === 1 ? reset($totals) : null,
            'totals_by_currency' => $totals,
        ];
    }

    /**
     * @return array{
     *     publishers: list<array{id: string, name: string}>,
     *     orders: LengthAwarePaginator,
     *     total_sum: float|null,
     *     commission_sum: float|null,
     *     totals_by_currency: array<string, array{total: float, commission: float}>
     * }
     */
    public function getAdminOrderTotals(
        ?string $publisherId,
        CarbonImmutable $start,
        CarbonImmutable $end,
        int $perPage = 25
    ): array {
        $publishers = Publisher::query()->orderBy('name')->get(['_id', 'name']);
        $publisherNames = [];
        $publisherOptions = [];
        foreach ($publishers as $publisher) {
            $id = (string) $publisher->getKey();
            $name = (string) $publisher->name;
            $publisherNames[$id] = $name;
            $publisherOptions[] = ['id' => $id, 'name' => $name];
        }

        if ($publisherId !== null && ! array_key_exists($publisherId, $publisherNames)) {
            throw new \InvalidArgumentException('Publisher not found.');
        }

        $warehouses = Warehouse::query()->get(['_id', 'publisher_id']);
        $publisherIdByWarehouse = [];
        foreach ($warehouses as $warehouse) {
            $ownerId = trim((string) ($warehouse->publisher_id ?? ''));
            if ($ownerId !== '' && isset($publisherNames[$ownerId])) {
                $publisherIdByWarehouse[(string) $warehouse->getKey()] = $ownerId;
            }
        }

        $warehouseIds = [];
        foreach ($publisherIdByWarehouse as $warehouseId => $ownerId) {
            if ($publisherId === null || $ownerId === $publisherId) {
                $warehouseIds[] = $warehouseId;
            }
        }

        $query = Order::query()
            ->whereIn('warehouse_id', $warehouseIds)
            ->whereBetween('created_at', [$start, $end])
            ->orderByDesc('created_at');
        $currencyCodes = $this->queryCurrencyCodes($query, $publishers->all());
        $totalsByCurrency = [];
        foreach ($currencyCodes as $currencyCode) {
            $currencyQuery = $this->currencyQuery(clone $query, $currencyCode);
            $totalsByCurrency[$currencyCode] = [
                'total' => CurrencyAmount::round((float) (clone $currencyQuery)->sum('total'), $currencyCode),
                'commission' => CurrencyAmount::round((float) (clone $currencyQuery)->sum('platform_commission_amount'), $currencyCode),
            ];
        }
        $orders = $query->paginate(min(max($perPage, 1), 100), [
            '_id',
            'total',
            'currency_code',
            'warehouse_id',
            'platform_commission_amount',
        ]);
        $orders->through(function (Order $order) use ($publisherIdByWarehouse, $publisherNames) {
            $ownerId = $publisherIdByWarehouse[(string) $order->warehouse_id] ?? '';

            return [
                'total' => (float) $order->total,
                'commission' => (float) ($order->platform_commission_amount ?? 0),
                'currency_code' => strtoupper((string) ($order->currency_code ?? 'USD')),
                'publisher_name' => $publisherNames[$ownerId] ?? '',
            ];
        });

        return [
            'publishers' => $publisherOptions,
            'orders' => $orders,
            'total_sum' => count($totalsByCurrency) === 1 ? array_values($totalsByCurrency)[0]['total'] : null,
            'commission_sum' => count($totalsByCurrency) === 1 ? array_values($totalsByCurrency)[0]['commission'] : null,
            'totals_by_currency' => $totalsByCurrency,
        ];
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
        string $currencyCode,
        float $owedDelta,
        float $accruedDelta,
        float $paidDelta
    ): void {
        $publisher = Publisher::query()->find($publisherId);
        if (! $publisher) {
            return;
        }

        $balances = $this->publisherBalances($publisher);
        $balance = $balances[$currencyCode] ?? [
            'commission_owed' => 0.0,
            'commission_accrued_total' => 0.0,
            'commission_paid_total' => 0.0,
        ];
        $balance['commission_owed'] = CurrencyAmount::round(max(0, $balance['commission_owed'] + $owedDelta), $currencyCode);
        $balance['commission_accrued_total'] = CurrencyAmount::round(max(0, $balance['commission_accrued_total'] + $accruedDelta), $currencyCode);
        $balance['commission_paid_total'] = CurrencyAmount::round(max(0, $balance['commission_paid_total'] + $paidDelta), $currencyCode);
        $balances[$currencyCode] = $balance;
        $publisher->commission_balances = $balances;
        $publisher->commission_owed = $balances['USD']['commission_owed'] ?? 0;
        $publisher->commission_accrued_total = $balances['USD']['commission_accrued_total'] ?? 0;
        $publisher->commission_paid_total = $balances['USD']['commission_paid_total'] ?? 0;
        $publisher->save();
    }

    /**
     * @return array<string, array{commission_owed: float, commission_accrued_total: float, commission_paid_total: float}>
     */
    private function publisherBalances(Publisher $publisher): array
    {
        $balances = is_array($publisher->commission_balances) ? $publisher->commission_balances : [];
        if ($balances === [] && (
            (float) ($publisher->commission_owed ?? 0) !== 0.0
            || (float) ($publisher->commission_accrued_total ?? 0) !== 0.0
            || (float) ($publisher->commission_paid_total ?? 0) !== 0.0
        )) {
            $balances['USD'] = [
                'commission_owed' => CurrencyAmount::round((float) ($publisher->commission_owed ?? 0), 'USD'),
                'commission_accrued_total' => CurrencyAmount::round((float) ($publisher->commission_accrued_total ?? 0), 'USD'),
                'commission_paid_total' => CurrencyAmount::round((float) ($publisher->commission_paid_total ?? 0), 'USD'),
            ];
        }

        foreach ($balances as $code => $balance) {
            $balances[$code] = [
                'commission_owed' => CurrencyAmount::round((float) ($balance['commission_owed'] ?? 0), (string) $code),
                'commission_accrued_total' => CurrencyAmount::round((float) ($balance['commission_accrued_total'] ?? 0), (string) $code),
                'commission_paid_total' => CurrencyAmount::round((float) ($balance['commission_paid_total'] ?? 0), (string) $code),
            ];
        }

        return $balances;
    }

    /**
     * @param  array<int, Publisher>  $publishers
     * @return list<string>
     */
    private function queryCurrencyCodes($query, array $publishers): array
    {
        $codes = ['USD'];
        foreach ($publishers as $publisher) {
            foreach (($publisher->settings['currencies'] ?? []) as $currency) {
                if (is_array($currency) && isset($currency['code'])) {
                    $codes[] = strtoupper((string) $currency['code']);
                }
            }
        }
        $codes = array_merge($codes, (clone $query)->distinct('currency_code')->pluck('currency_code')->all());

        return array_values(array_unique(array_filter($codes, static fn ($code) => is_string($code) && preg_match('/^[A-Z]{3}$/', $code))));
    }

    private function sumByCurrency($query, array $currencyCodes, string $field): array
    {
        $totals = [];
        foreach ($currencyCodes as $currencyCode) {
            $totals[$currencyCode] = CurrencyAmount::round(
                (float) $this->currencyQuery(clone $query, $currencyCode)->sum($field),
                $currencyCode
            );
        }
        $totals = array_filter($totals, static fn ($amount) => $amount !== 0.0);

        return $totals;
    }

    private function currencyQuery($query, string $currencyCode)
    {
        return $currencyCode === 'USD'
            ? $query->where(fn ($q) => $q->where('currency_code', 'USD')->orWhereNull('currency_code'))
            : $query->where('currency_code', $currencyCode);
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
