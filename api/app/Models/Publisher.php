<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use MongoDB\Laravel\Eloquent\Model;

class Publisher extends Model
{
    protected $connection = 'mongodb';

    protected $table = 'publishers';

    protected $fillable = [
        'name',
        'address',
        'phone',
        'email',
        'website',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }
}
