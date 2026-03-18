<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

class City extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'cities';

    protected $fillable = ['name', 'countryId', 'translations'];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'countryId', '_id');
    }

    protected function casts(): array
    {
        return [
            'translations' => 'array',
        ];
    }
}
