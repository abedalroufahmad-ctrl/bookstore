<?php

namespace App\Models;

use App\Models\Concerns\BustsCatalogCache;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\PresentsDocumentId;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use PresentsDocumentId;

    use BustsCatalogCache;

    protected $table = 'categories';

    protected $fillable = [
        'dewey_code',
        'subject_title_en',
        'subject_title_ar',
        'subject_number',
    ];

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }
}
