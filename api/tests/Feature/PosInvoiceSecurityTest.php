<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Employee;
use App\Models\Publisher;
use App\Models\Setting;
use App\Models\Warehouse;

class PosInvoiceSecurityTest extends MongoFeatureTestCase
{
    public function test_direct_sales_invoice_responses_hide_internal_payout_fields(): void
    {
        $publisher = Publisher::create([
            'name' => 'Private Press',
            'settings' => [
                'platform_commission_percent' => 12,
                'paypal_email' => 'private-payout@press.test',
                'paypal_merchant_id' => 'PRIVATE-MERCHANT-ID',
            ],
        ]);
        $warehouse = Warehouse::create([
            'name' => 'Sales Warehouse',
            'publisher_id' => (string) $publisher->getKey(),
        ]);
        $employee = Employee::create([
            'name' => 'Direct Sales',
            'email' => 'direct-sales-'.uniqid().'@staff.test',
            'password' => 'secret123',
            'role' => 'direct_sales',
            'warehouse_id' => (string) $warehouse->getKey(),
        ]);
        $book = Book::create([
            'title' => 'POS security test book',
            'price' => 10,
            'stock_quantity' => 2,
            'condition' => 'new',
            'is_visible' => true,
            'is_sold' => false,
            'warehouse_id' => (string) $warehouse->getKey(),
            'publisher_id' => (string) $publisher->getKey(),
            'publisher_ids' => [(string) $publisher->getKey()],
        ]);
        Setting::set('invoice_shipping_fee', 0);

        $headers = $this->employeeHeaders($employee);
        $invoiceUrl = '/api/v1/admin/pos/invoices';
        $payload = [
            'items' => [['book_id' => (string) $book->getKey(), 'quantity' => 1]],
            'warehouse_id' => (string) $warehouse->getKey(),
        ];

        $created = $this->postJson($invoiceUrl, $payload, $headers)
            ->assertCreated()
            ->assertJsonMissingPath('data.platform_commission_percent')
            ->assertJsonMissingPath('data.platform_commission_amount')
            ->assertJsonMissingPath('data.publisher_payout_amount')
            ->assertJsonMissingPath('data.payout_paypal_email')
            ->assertJsonMissingPath('data.payout_paypal_merchant_id');
        $invoiceId = $created->json('data._id');

        $this->getJson($invoiceUrl, $headers)
            ->assertOk()
            ->assertJsonMissingPath('data.0.platform_commission_percent')
            ->assertJsonMissingPath('data.0.platform_commission_amount')
            ->assertJsonMissingPath('data.0.publisher_payout_amount')
            ->assertJsonMissingPath('data.0.payout_paypal_email')
            ->assertJsonMissingPath('data.0.payout_paypal_merchant_id');

        $this->getJson($invoiceUrl.'/'.$invoiceId, $headers)
            ->assertOk()
            ->assertJsonMissingPath('data.platform_commission_percent')
            ->assertJsonMissingPath('data.platform_commission_amount')
            ->assertJsonMissingPath('data.publisher_payout_amount')
            ->assertJsonMissingPath('data.payout_paypal_email')
            ->assertJsonMissingPath('data.payout_paypal_merchant_id');
    }
}
