<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\PresentsDocumentId;
use Illuminate\Database\Eloquent\Model;

class PublisherCommissionEntry extends Model
{
    use PresentsDocumentId;

    public const TYPE_SALE_ACCRUAL = 'sale_accrual';

    public const TYPE_SETTLEMENT = 'settlement';

    protected $table = 'publisher_commission_entries';

    protected $fillable = [
        'publisher_id',
        'order_id',
        'type',
        'amount',
        'currency_code',
        'note',
        'reference',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }
}
