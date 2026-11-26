<?php

namespace App\Services;

use Carbon\Carbon;
use App\Support\CurrencyAmount;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;

class PosReportAggregator
{
    /**
     * @param  iterable<mixed>  $orders
     * @return array{
     *     summary: array<string, array{period: string, total: float|null, totals_by_currency: array<string, float>, count: int}>,
     *     periods: list<array{period: string, total: float|null, totals_by_currency: array<string, float>, count: int}>,
     *     type: string
     * }
     */
    public function aggregate(iterable $orders, string $type, DateTimeZone $timezone): array
    {
        $days = [];
        foreach ($orders as $order) {
            $total = (float) (is_object($order) ? ($order->total ?? 0) : ($order['total'] ?? 0));
            $currencyCode = strtoupper((string) (is_object($order)
                ? ($order->currency_code ?? 'USD')
                : ($order['currency_code'] ?? 'USD')));
            $rawCreated = is_object($order) ? ($order->created_at ?? null) : ($order['created_at'] ?? null);
            $day = $this->toLocalCarbon($rawCreated, $timezone)?->format('Y-m-d');

            $key = $day ?? '';
            $days[$key] ??= ['day' => $day, 'totals_by_currency' => [], 'count' => 0];
            $days[$key]['totals_by_currency'][$currencyCode] =
                ($days[$key]['totals_by_currency'][$currencyCode] ?? 0.0) + $total;
            $days[$key]['count']++;
        }

        return $this->aggregateDailyBuckets(array_values($days), $type, $timezone);
    }

    /**
     * Groups matching orders by local calendar day inside MongoDB, so reports never
     * load every invoice into PHP memory.
     *
     * @return list<array{day: ?string, currency_code: string, total: float, count: int}>
     */
    public function fetchDailyBuckets(Builder $query, DateTimeZone $timezone): array
    {
        $filter = $query->toBase()->toMql()['find'][0] ?? [];

        $pipeline = [
            ['$match' => (object) $filter],
            ['$group' => [
                '_id' => [
                    'day' => ['$dateToString' => [
                        'format' => '%Y-%m-%d',
                        'date' => '$created_at',
                        'timezone' => $timezone->getName(),
                        'onNull' => null,
                    ]],
                    'currency_code' => ['$ifNull' => ['$currency_code', 'USD']],
                ],
                'total' => ['$sum' => ['$convert' => [
                    'input' => '$total', 'to' => 'double', 'onError' => 0, 'onNull' => 0,
                ]]],
                'count' => ['$sum' => 1],
            ]],
        ];

        $cursor = $query->getModel()->getConnection()
            ->getCollection($query->getModel()->getTable())
            ->aggregate($pipeline);

        $buckets = [];
        foreach ($cursor as $row) {
            $buckets[] = [
                'day' => is_string(data_get($row, '_id.day')) ? data_get($row, '_id.day') : null,
                'currency_code' => strtoupper((string) (data_get($row, '_id.currency_code') ?? 'USD')),
                'total' => (float) ($row['total'] ?? 0),
                'count' => (int) ($row['count'] ?? 0),
            ];
        }

        return $buckets;
    }

    /**
     * @param  iterable<array{day: ?string, total?: float, totals_by_currency?: array<string, float>, currency_code?: string, count: int}>  $days  local Y-m-d buckets (day null = unknown date)
     */
    public function aggregateDailyBuckets(iterable $days, string $type, DateTimeZone $timezone): array
    {
        if (! in_array($type, ['daily', 'monthly', 'yearly'], true)) {
            $type = 'daily';
        }

        $now = Carbon::now($timezone);
        $todayKey = $now->format('Y-m-d');
        $monthKey = $now->format('Y-m');
        $yearKey = $now->format('Y');

        $summary = [
            'today' => ['period' => $todayKey, 'totals_by_currency' => [], 'count' => 0],
            'month' => ['period' => $monthKey, 'totals_by_currency' => [], 'count' => 0],
            'year' => ['period' => $yearKey, 'totals_by_currency' => [], 'count' => 0],
            'all' => ['period' => 'all', 'totals_by_currency' => [], 'count' => 0],
        ];
        $periods = [];

        foreach ($days as $bucket) {
            $totalsByCurrency = $bucket['totals_by_currency'] ?? [
                strtoupper((string) ($bucket['currency_code'] ?? 'USD')) => (float) ($bucket['total'] ?? 0),
            ];
            $count = (int) $bucket['count'];
            $this->addCurrencyTotals($summary['all']['totals_by_currency'], $totalsByCurrency);
            $summary['all']['count'] += $count;

            $day = $bucket['day'];
            if (! is_string($day) || strlen($day) !== 10) {
                continue;
            }

            $month = substr($day, 0, 7);
            $year = substr($day, 0, 4);
            foreach (['today' => $day === $todayKey, 'month' => $month === $monthKey, 'year' => $year === $yearKey] as $name => $hit) {
                if ($hit) {
                    $this->addCurrencyTotals($summary[$name]['totals_by_currency'], $totalsByCurrency);
                    $summary[$name]['count'] += $count;
                }
            }

            $key = match ($type) {
                'monthly' => $month,
                'yearly' => $year,
                default => $day,
            };
            $periods[$key] ??= ['period' => $key, 'totals_by_currency' => [], 'count' => 0];
            $this->addCurrencyTotals($periods[$key]['totals_by_currency'], $totalsByCurrency);
            $periods[$key]['count'] += $count;
        }

        krsort($periods);

        foreach ($summary as &$bucket) {
            $bucket = $this->formatBucketTotals($bucket);
        }
        unset($bucket);

        $periodList = array_map(fn (array $bucket): array => $this->formatBucketTotals($bucket), array_values($periods));

        return [
            'summary' => $summary,
            'periods' => $periodList,
            'type' => $type,
        ];
    }

    /**
     * @param  array<string, float>  $target
     * @param  array<string, float>  $values
     */
    private function addCurrencyTotals(array &$target, array $values): void
    {
        foreach ($values as $currencyCode => $amount) {
            $currencyCode = strtoupper((string) $currencyCode);
            $target[$currencyCode] = ($target[$currencyCode] ?? 0.0) + (float) $amount;
        }
    }

    /**
     * @param  array{period: string, totals_by_currency: array<string, float>, count: int}  $bucket
     * @return array{period: string, total: float|null, totals_by_currency: array<string, float>, count: int}
     */
    private function formatBucketTotals(array $bucket): array
    {
        $totals = [];
        foreach ($bucket['totals_by_currency'] as $currencyCode => $amount) {
            $rounded = CurrencyAmount::round((float) $amount, $currencyCode);
            if ($rounded !== 0.0) {
                $totals[$currencyCode] = $rounded;
            }
        }
        ksort($totals);
        $bucket['totals_by_currency'] = $totals;
        $bucket['total'] = count($totals) === 1 ? reset($totals) : (count($totals) === 0 ? 0.0 : null);

        return $bucket;
    }

    public function resolveTimezone(mixed $timezoneName, mixed $utcOffsetMinutes): DateTimeZone
    {
        if (is_string($timezoneName) && $timezoneName !== '') {
            try {
                return new DateTimeZone($timezoneName);
            } catch (\Throwable) {
                // Fall through to offset or app timezone.
            }
        }

        if (is_numeric($utcOffsetMinutes)) {
            $minutes = (int) $utcOffsetMinutes;
            if ($minutes >= -14 * 60 && $minutes <= 14 * 60) {
                $sign = $minutes >= 0 ? '+' : '-';
                $abs = abs($minutes);

                return new DateTimeZone(sprintf('%s%02d:%02d', $sign, intdiv($abs, 60), $abs % 60));
            }
        }

        return new DateTimeZone((string) config('app.timezone', 'UTC'));
    }

    public function toLocalCarbon(mixed $createdAt, DateTimeZone $timezone): ?Carbon
    {
        try {
            if ($createdAt instanceof Carbon) {
                $date = $createdAt->copy();
            } elseif ($createdAt instanceof DateTimeInterface) {
                $date = Carbon::instance(\DateTimeImmutable::createFromInterface($createdAt));
            } elseif (is_object($createdAt) && method_exists($createdAt, 'toDateTime')) {
                $converted = $createdAt->toDateTime();
                if (! $converted instanceof DateTimeInterface) {
                    return null;
                }
                $date = Carbon::instance(\DateTimeImmutable::createFromInterface($converted));
            } elseif (is_string($createdAt) || is_numeric($createdAt)) {
                $date = Carbon::parse($createdAt);
            } else {
                return null;
            }

            return $date->setTimezone($timezone);
        } catch (\Throwable) {
            return null;
        }
    }
}
