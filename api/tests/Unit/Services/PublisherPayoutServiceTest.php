<?php

namespace Tests\Unit\Services;

use App\Services\PublisherPayoutService;
use PHPUnit\Framework\TestCase;

class PublisherPayoutServiceTest extends TestCase
{
    private PublisherPayoutService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PublisherPayoutService;
    }

    public function test_splits_book_revenue_using_agreed_percent(): void
    {
        $snap = $this->service->snapshotFromSettings([
            'platform_commission_percent' => 10,
            'paypal_email' => 'house@paypal.test',
            'paypal_merchant_id' => 'MERCHANT1',
        ], 100);

        $this->assertSame(10.0, $snap['platform_commission_percent']);
        $this->assertSame(10.0, $snap['platform_commission_amount']);
        $this->assertSame(90.0, $snap['publisher_payout_amount']);
        $this->assertSame('house@paypal.test', $snap['payout_paypal_email']);
        $this->assertSame('MERCHANT1', $snap['payout_paypal_merchant_id']);
    }

    public function test_zero_percent_sends_full_amount_to_publisher(): void
    {
        $snap = $this->service->snapshotFromSettings([], 49.5);

        $this->assertSame(0.0, $snap['platform_commission_percent']);
        $this->assertSame(0.0, $snap['platform_commission_amount']);
        $this->assertSame(49.5, $snap['publisher_payout_amount']);
        $this->assertNull($snap['payout_paypal_email']);
    }

    public function test_percent_is_clamped_and_rounded(): void
    {
        $this->assertSame(0.0, $this->service->normalizePercent(-5));
        $this->assertSame(100.0, $this->service->normalizePercent(150));
        $this->assertSame(12.34, $this->service->normalizePercent(12.344));
    }
}
