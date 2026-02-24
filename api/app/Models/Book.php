<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use MongoDB\Laravel\Eloquent\Model;

class Book extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'books';

    protected $fillable = [
        'title',
        'author_ids',
        'category_id',
        'size',
        'weight',
        'cover_image',
        'cover_image_thumb',
        'description',
        'price',
        'pages',
        'isbn',
        'publish_year',
        'edition_number',
        'binding_type',
        'paper_type',
        'publisher',
        'warehouse_id',
        'stock_quantity',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'pages' => 'integer',
            'stock_quantity' => 'integer',
            'publish_year' => 'integer',
            'edition_number' => 'integer',
        ];
    }

    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class, null, 'author_ids');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
