<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use MongoDB\Laravel\Eloquent\Model;

class Category extends Model
{
    protected $connection = 'mongodb';

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
