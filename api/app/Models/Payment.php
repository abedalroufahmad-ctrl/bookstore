<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

class Payment extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'payments';

    protected $fillable = [
        'order_id',
        'user_id',
        'payment_method',
        'payment_status',
        'transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public static function statusPending(): string
    {
        return 'pending';
    }

    public static function statusPaid(): string
    {
        return 'paid';
    }

    public static function statusFailed(): string
    {
        return 'failed';
    }
}
