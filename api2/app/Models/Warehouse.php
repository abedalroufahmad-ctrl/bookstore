<?php

namespace App\Models;

use App\Models\Concerns\BustsCatalogCache;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\PresentsDocumentId;
use Illuminate\Database\Eloquent\Model;

/**
 * location: GeoJSON Point { type: "Point", coordinates: [lng, lat] } for 2dsphere index
 */
class Warehouse extends Model
{
    use PresentsDocumentId;

    use BustsCatalogCache;

    protected $table = 'warehouses';

    protected $fillable = [
        'name',
        'address',
        'country',
        'city',
        'phone',
        'email',
        'location',
        'payment_methods',
        'shipping_options',
        'manager_id',
        'publisher_id',
    ];

    protected function casts(): array
    {
        return [
            'payment_methods' => 'array',
            'shipping_options' => 'array',
            'location' => 'array',
        ];
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class);
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
