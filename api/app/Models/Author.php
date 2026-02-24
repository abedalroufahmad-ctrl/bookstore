<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use MongoDB\Laravel\Eloquent\Model;

class Author extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'authors';

    protected $fillable = [
        'name',
        'biography',
    ];

    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, null, 'author_ids');
    }
}
