<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use MongoDB\Laravel\Eloquent\Model;

/**
 * location: GeoJSON Point { type: "Point", coordinates: [lng, lat] } for 2dsphere index
 */
class Warehouse extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'warehouses';

    protected $fillable = [
        'name',
        'address',
        'country',
        'city',
        'phone',
        'email',
        'location',
    ];

    protected function casts(): array
    {
        return [];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }
}
