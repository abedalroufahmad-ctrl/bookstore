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
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'books_subtotal' => 'float',
            'shipping_fee' => 'float',
            'total' => 'float',
            'shipping_address' => 'array',
            'payment_info' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
