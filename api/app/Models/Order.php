<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Order items: [{book_id, quantity, price}]. shipping_address: {address, city, country, ...}
 */
class Order extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'orders';

    protected $fillable = [
        'customer_id',
        'employee_id',
        'items',
        'status',
        'total',
        'shipping_address',
        'payment_info',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
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
