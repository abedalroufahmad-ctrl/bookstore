<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Services\PosReportAggregator;
use Carbon\Carbon;
use DateTimeZone;
use MongoDB\BSON\UTCDateTime;

class PosReportAggregationTest extends MongoFeatureTestCase
{
    public function test_database_aggregation_matches_in_memory_aggregation(): void
    {
        $rows = [
            ['total' => 100, 'created_at' => '2026-09-02T18:59:10Z', 'warehouse_id' => 'w1'],
            ['total' => 50.5, 'created_at' => '2026-09-01T22:49:08Z', 'warehouse_id' => 'w1'], // next local day at +03:00
            ['total' => 20, 'created_at' => '2026-09-01T17:02:29Z', 'warehouse_id' => 'w1'],
            ['total' => 7, 'created_at' => '2025-12-31T22:30:00Z', 'warehouse_id' => 'w1'], // 2026-01-01 local
            ['total' => 999, 'created_at' => '2026-09-02T10:00:00Z', 'warehouse_id' => 'other'],
        ];
        foreach ($rows as $row) {
            $order = Order::create([
                'is_direct_sale' => true,
                'warehouse_id' => $row['warehouse_id'],
                'total' => $row['total'],
                'items' => [],
                'status' => 'completed',
            ]);
            Order::query()->whereKey($order->getKey())->update([
                'created_at' => new UTCDateTime(Carbon::parse($row['created_at'])),
            ]);
        }
        Order::create(['is_direct_sale' => false, 'warehouse_id' => 'w1', 'total' => 5000, 'items' => [], 'status' => 'completed']);

        $aggregator = new PosReportAggregator;
        $tz = new DateTimeZone('+03:00');
        $query = fn () => Order::where('is_direct_sale', true)->whereIn('warehouse_id', ['w1']);

        foreach (['daily', 'monthly', 'yearly'] as $type) {
            $expected = $aggregator->aggregate($query()->get(['total', 'created_at']), $type, $tz);
            $actual = $aggregator->aggregateDailyBuckets($aggregator->fetchDailyBuckets($query(), $tz), $type, $tz);

            $this->assertEquals($expected, $actual, "Mismatch for {$type} report.");
        }

        $daily = $aggregator->aggregateDailyBuckets($aggregator->fetchDailyBuckets($query(), $tz), 'daily', $tz);
        $byDay = array_column($daily['periods'], null, 'period');
        $this->assertSame(2, $byDay['2026-09-02']['count']);
        $this->assertSame(150.5, $byDay['2026-09-02']['total']);
        $this->assertSame(1, $byDay['2026-01-01']['count']);
        $this->assertSame(4, $daily['summary']['all']['count']);
        $this->assertSame(177.5, $daily['summary']['all']['total']);
    }
}
