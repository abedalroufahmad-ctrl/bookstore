<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Order items (stored): [{book_id, quantity, price}].
 * API responses may include book_title on each row when the order is loaded via OrderService::getOrderById().
 */
class Order extends Model
{
    protected $connection = 'mongodb';

    protected $table = 'orders';

    protected $fillable = [
        'customer_id',
        'customer_name', // for walk-in direct sales
        'is_direct_sale', // boolean flag for POS orders
        'employee_id',
        'warehouse_id',
        'items',
        'status',
        'books_subtotal',
        'shipping_fee',
        'shipping_method',
        'total',
        'shipping_address',
        'payment_info',
        'payment_method',
        'payment_status',
        'platform_commission_percent',
        'platform_commission_amount',
        'publisher_payout_amount',
        'payout_paypal_email',
        'payout_paypal_merchant_id',
    ];

    /** @var list<string> */
    public const INTERNAL_PAYOUT_FIELDS = [
        'platform_commission_percent',
        'platform_commission_amount',
        'publisher_payout_amount',
        'payout_paypal_email',
        'payout_paypal_merchant_id',
    ];

    protected function casts(): array
    {
        return [
            'is_direct_sale' => 'boolean',
            'items' => 'array',
            'books_subtotal' => 'float',
            'shipping_fee' => 'float',
            'total' => 'float',
            'shipping_address' => 'array',
            'payment_info' => 'array',
            'platform_commission_percent' => 'float',
            'platform_commission_amount' => 'float',
            'publisher_payout_amount' => 'float',
        ];
    }

    public function hideInternalPayouts(): static
    {
        return $this->makeHidden(self::INTERNAL_PAYOUT_FIELDS);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
