<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Publisher extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'publishers';

    protected $fillable = [
        'name',
        'address',
        'phone',
        'email',
        'website',
    ];
}
