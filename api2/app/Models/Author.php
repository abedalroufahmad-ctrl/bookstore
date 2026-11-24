<?php

namespace App\Models;

use App\Models\Concerns\BustsCatalogCache;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Concerns\PresentsDocumentId;
use Illuminate\Database\Eloquent\Model;

class Author extends Model
{
    use PresentsDocumentId;

    use BustsCatalogCache;

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
        return $this->belongsToMany(Book::class, 'author_book');
    }
}
