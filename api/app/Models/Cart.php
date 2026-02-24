<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Cart contains items as array of: [{book_id, quantity, price}]
 */
class Cart extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'carts';

    protected $fillable = [
        'customer_id',
        'items',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
