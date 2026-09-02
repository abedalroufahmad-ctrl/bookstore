<?php

namespace App\Services;

use Carbon\Carbon;
use DateTimeInterface;
use DateTimeZone;

class PosReportAggregator
{
    /**
     * @param  iterable<mixed>  $orders
     * @return array{
     *     summary: array<string, array{period: string, total: float, count: int}>,
     *     periods: list<array{period: string, total: float, count: int}>,
     *     type: string
     * }
     */
    public function aggregate(iterable $orders, string $type, DateTimeZone $timezone): array
    {
        if (! in_array($type, ['daily', 'monthly', 'yearly'], true)) {
            $type = 'daily';
        }

        $now = Carbon::now($timezone);
        $todayKey = $now->format('Y-m-d');
        $monthKey = $now->format('Y-m');
        $yearKey = $now->format('Y');

        $summary = [
            'today' => ['period' => $todayKey, 'total' => 0.0, 'count' => 0],
            'month' => ['period' => $monthKey, 'total' => 0.0, 'count' => 0],
            'year' => ['period' => $yearKey, 'total' => 0.0, 'count' => 0],
            'all' => ['period' => 'all', 'total' => 0.0, 'count' => 0],
        ];
        $periods = [];

        foreach ($orders as $order) {
            $total = (float) (is_object($order) ? ($order->total ?? 0) : ($order['total'] ?? 0));
            $summary['all']['total'] += $total;
            $summary['all']['count']++;

            $rawCreated = is_object($order) ? ($order->created_at ?? null) : ($order['created_at'] ?? null);
            $date = $this->toLocalCarbon($rawCreated, $timezone);
            if ($date === null) {
                continue;
            }

            if ($date->format('Y-m-d') === $todayKey) {
                $summary['today']['total'] += $total;
                $summary['today']['count']++;
            }
            if ($date->format('Y-m') === $monthKey) {
                $summary['month']['total'] += $total;
                $summary['month']['count']++;
            }
            if ($date->format('Y') === $yearKey) {
                $summary['year']['total'] += $total;
                $summary['year']['count']++;
            }

            $key = match ($type) {
                'monthly' => $date->format('Y-m'),
                'yearly' => $date->format('Y'),
                default => $date->format('Y-m-d'),
            };
            if (! isset($periods[$key])) {
                $periods[$key] = ['period' => $key, 'total' => 0.0, 'count' => 0];
            }
            $periods[$key]['total'] += $total;
            $periods[$key]['count']++;
        }

        krsort($periods);

        foreach ($summary as &$bucket) {
            $bucket['total'] = round((float) $bucket['total'], 2);
        }
        unset($bucket);

        $periodList = array_map(static function (array $bucket): array {
            $bucket['total'] = round((float) $bucket['total'], 2);

            return $bucket;
        }, array_values($periods));

        return [
            'summary' => $summary,
            'periods' => $periodList,
            'type' => $type,
        ];
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
