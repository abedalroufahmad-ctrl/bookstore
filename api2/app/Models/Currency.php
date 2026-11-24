<?php

namespace App\Models;

use App\Models\Concerns\PresentsDocumentId;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use PresentsDocumentId;

    protected $table = 'currencies';

    protected $fillable = [
        'code', 'symbol', 'name', 'nameArabic',
        'exchangeRate', 'isActive', 'isDefault', 'countryCode',
    ];

    protected function casts(): array
    {
        return [
            'exchangeRate' => 'float',
            'isActive' => 'boolean',
            'isDefault' => 'boolean',
        ];
    }
}
