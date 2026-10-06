<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Publisher;
use App\Models\Warehouse;

class PublisherPayoutService
{
    /**
     * Commission is taken from book revenue only (not shipping).
     *
     * @return array{
     *     platform_commission_percent: float,
     *     platform_commission_amount: float,
     *     publisher_payout_amount: float,
     *     payout_paypal_email: string|null,
     *     payout_paypal_merchant_id: string|null
     * }
     */
    public function snapshotForWarehouse(string $warehouseId, float $booksSubtotal): array
    {
        $warehouse = Warehouse::query()->with('publisher')->find($warehouseId);
        $publisher = $warehouse?->publisher instanceof Publisher ? $warehouse->publisher : null;

        return $this->snapshotForPublisher($publisher, $booksSubtotal);
    }

    /**
     * @return array{
     *     platform_commission_percent: float,
     *     platform_commission_amount: float,
     *     publisher_payout_amount: float,
     *     payout_paypal_email: string|null,
     *     payout_paypal_merchant_id: string|null
     * }
     */
    public function snapshotForPublisher(?Publisher $publisher, float $booksSubtotal): array
    {
        $settings = is_array($publisher?->settings) ? $publisher->settings : [];

        return $this->snapshotFromSettings($settings, $booksSubtotal);
    }

    /**
     * Recalculate money amounts using a rate already frozen on the order.
     *
     * @return array{
     *     platform_commission_percent: float,
     *     platform_commission_amount: float,
     *     publisher_payout_amount: float,
     *     payout_paypal_email: string|null,
     *     payout_paypal_merchant_id: string|null
     * }
     */
    public function snapshotForOrder(Order $order, float $booksSubtotal): array
    {
        $percent = $order->platform_commission_percent;
        if ($percent === null || $percent === '') {
            return $this->snapshotForWarehouse((string) ($order->warehouse_id ?? ''), $booksSubtotal);
        }

        $amounts = $this->amountsForPercent($percent, $booksSubtotal);

        return [
            'platform_commission_percent' => $amounts['platform_commission_percent'],
            'platform_commission_amount' => $amounts['platform_commission_amount'],
            'publisher_payout_amount' => $amounts['publisher_payout_amount'],
            'payout_paypal_email' => $this->nullableString($order->payout_paypal_email ?? null),
            'payout_paypal_merchant_id' => $this->nullableString($order->payout_paypal_merchant_id ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{
     *     platform_commission_percent: float,
     *     platform_commission_amount: float,
     *     publisher_payout_amount: float,
     *     payout_paypal_email: string|null,
     *     payout_paypal_merchant_id: string|null
     * }
     */
    public function snapshotFromSettings(array $settings, float $booksSubtotal): array
    {
        $amounts = $this->amountsForPercent($settings['platform_commission_percent'] ?? 0, $booksSubtotal);

        return [
            'platform_commission_percent' => $amounts['platform_commission_percent'],
            'platform_commission_amount' => $amounts['platform_commission_amount'],
            'publisher_payout_amount' => $amounts['publisher_payout_amount'],
            'payout_paypal_email' => $this->nullableString($settings['paypal_email'] ?? null),
            'payout_paypal_merchant_id' => $this->nullableString($settings['paypal_merchant_id'] ?? null),
        ];
    }

    /**
     * @return array{platform_commission_percent: float, platform_commission_amount: float, publisher_payout_amount: float}
     */
    public function amountsForPercent(mixed $percent, float $booksSubtotal): array
    {
        $rate = $this->normalizePercent($percent);
        $booksSubtotal = round(max(0, $booksSubtotal), 2);
        $commission = round($booksSubtotal * ($rate / 100), 2);
        if ($commission > $booksSubtotal) {
            $commission = $booksSubtotal;
        }

        return [
            'platform_commission_percent' => $rate,
            'platform_commission_amount' => $commission,
            'publisher_payout_amount' => round($booksSubtotal - $commission, 2),
        ];
    }

    /**
     * @return array{email: string|null, merchant_id: string|null}
     */
    public function paypalPayeeForOrder(Order $order): array
    {
        $email = $this->nullableString($order->payout_paypal_email ?? null);
        $merchantId = $this->nullableString($order->payout_paypal_merchant_id ?? null);
        if ($email !== null || $merchantId !== null) {
            return ['email' => $email, 'merchant_id' => $merchantId];
        }

        $warehouseId = trim((string) ($order->warehouse_id ?? ''));
        if ($warehouseId === '') {
            return ['email' => null, 'merchant_id' => null];
        }

        $snap = $this->snapshotForWarehouse($warehouseId, 0);

        return [
            'email' => $snap['payout_paypal_email'],
            'merchant_id' => $snap['payout_paypal_merchant_id'],
        ];
    }

    public function normalizePercent(mixed $value): float
    {
        if (! is_numeric($value)) {
            return 0.0;
        }

        $percent = (float) $value;
        if ($percent < 0) {
            return 0.0;
        }
        if ($percent > 100) {
            return 100.0;
        }

        return round($percent, 2);
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
