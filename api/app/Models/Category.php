<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use MongoDB\Laravel\Eloquent\Model;

class Category extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'categories';

    protected $fillable = [
        'dewey_code',
        'subject_title',
        'subject_number',
    ];

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }
}
