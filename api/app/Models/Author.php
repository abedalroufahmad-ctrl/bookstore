<?php

namespace App\Models;

use App\Models\Concerns\BustsCatalogCache;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use MongoDB\Laravel\Eloquent\Model;

class Author extends Model
{
    use BustsCatalogCache;

    protected $connection = 'mongodb';

    protected $table = 'authors';

    protected $fillable = [
        'name',
        'biography',
        'date_of_birth',
        'date_of_death',
        'photo',
    ];

    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, null, 'author_ids');
    }
}
