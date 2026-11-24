<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\PresentsDocumentId;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use PresentsDocumentId;

    protected $table = 'payments';

    protected $fillable = [
        'order_id',
        'user_id',
        'amount',
        'currency',
        'method',
        'status',
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
