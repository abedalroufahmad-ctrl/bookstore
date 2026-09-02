<?php

namespace Tests\Unit\Services;

use App\Services\PosReportAggregator;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

class PosReportAggregatorTest extends TestCase
{
    public function test_daily_buckets_use_local_timezone_not_utc(): void
    {
        $aggregator = new PosReportAggregator;
        $tz = new DateTimeZone('+03:00');

        // Same invoices as the POS list: stored in UTC, shown locally as UTC+3.
        $orders = [
            (object) ['total' => 1245354, 'created_at' => '2026-09-02T18:59:10Z'], // 9:59 PM local 9/2
            (object) ['total' => 1238875, 'created_at' => '2026-09-02T11:12:32Z'], // 2:12 PM local 9/2
            (object) ['total' => 2494412, 'created_at' => '2026-09-01T22:49:08Z'], // 1:49 AM local 9/2
            (object) ['total' => 3729427, 'created_at' => '2026-09-01T21:59:37Z'], // 12:59 AM local 9/2
            (object) ['total' => 3709937, 'created_at' => '2026-09-01T21:59:06Z'], // 12:59 AM local 9/2
            (object) ['total' => 2475330, 'created_at' => '2026-09-01T17:02:29Z'], // 8:02 PM local 9/1
        ];

        $result = $aggregator->aggregate($orders, 'daily', $tz);
        $byPeriod = [];
        foreach ($result['periods'] as $bucket) {
            $byPeriod[$bucket['period']] = $bucket;
        }

        $this->assertSame(5, $byPeriod['2026-09-02']['count']);
        $this->assertSame(12418005.0, $byPeriod['2026-09-02']['total']);
        $this->assertSame(1, $byPeriod['2026-09-01']['count']);
        $this->assertSame(2475330.0, $byPeriod['2026-09-01']['total']);
    }

    public function test_utc_grouping_would_split_the_local_day(): void
    {
        $aggregator = new PosReportAggregator;
        $result = $aggregator->aggregate([
            (object) ['total' => 100, 'created_at' => '2026-09-01T21:59:06Z'],
            (object) ['total' => 200, 'created_at' => '2026-09-02T11:12:32Z'],
        ], 'daily', new DateTimeZone('UTC'));

        $byPeriod = [];
        foreach ($result['periods'] as $bucket) {
            $byPeriod[$bucket['period']] = $bucket;
        }

        $this->assertSame(1, $byPeriod['2026-09-01']['count']);
        $this->assertSame(1, $byPeriod['2026-09-02']['count']);
    }

    public function test_resolve_timezone_from_iana_and_offset(): void
    {
        $aggregator = new PosReportAggregator;

        $this->assertSame('Asia/Amman', $aggregator->resolveTimezone('Asia/Amman', null)->getName());
        $this->assertSame('+03:00', $aggregator->resolveTimezone(null, 180)->getName());
        $this->assertSame('-05:00', $aggregator->resolveTimezone('not-a-zone', -300)->getName());
    }
}
