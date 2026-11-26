<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\PresentsDocumentId;
use Illuminate\Database\Eloquent\Model;

/**
 * Cart contains items as array of: [{book_id, quantity, price}]
 */
class Cart extends Model
{
    use PresentsDocumentId;

    protected $table = 'carts';

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
