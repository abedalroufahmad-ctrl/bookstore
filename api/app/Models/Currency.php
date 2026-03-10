<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Currency extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'currencies';

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
