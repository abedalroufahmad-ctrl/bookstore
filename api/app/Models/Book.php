<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use MongoDB\Laravel\Eloquent\Model;

class Book extends Model
{
    protected $connection = 'mongodb';

    protected $table = 'books';

    public $timestamps = true;

    protected $fillable = [
        'title',
        'author_ids',
        'category_id',
        'size',
        'weight',
        'cover_image',
        'cover_image_thumb',
        'has_cover',
        'description',
        'price',
        'pages',
        'isbn',
        'publish_year',
        'edition_number',
        'binding_type',
        'paper_type',
        'publisher_id',
        'warehouse_id',
        'stock_quantity',
        'discount_percent',
        'condition',
        'is_visible',
        'is_sold',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'pages' => 'integer',
            'stock_quantity' => 'integer',
            'publish_year' => 'integer',
            'edition_number' => 'integer',
            'discount_percent' => 'float',
            'has_cover' => 'boolean',
            'is_visible' => 'boolean',
            'is_sold' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Book $book) {
            $cover = trim((string) ($book->cover_image ?? ''));
            $thumb = trim((string) ($book->cover_image_thumb ?? ''));
            $book->has_cover = self::isRealCoverUrl($cover) || self::isRealCoverUrl($thumb);

            if ($book->condition === null || $book->condition === '') {
                $book->condition = 'new';
            }
            if ($book->is_visible === null) {
                $book->is_visible = true;
            }
            if ($book->is_sold === null) {
                $book->is_sold = false;
            }

            // Used copies are unique inventory units.
            if ($book->condition === 'used' && (int) $book->stock_quantity > 1) {
                $book->stock_quantity = 1;
            }
            if ($book->condition === 'used' && (bool) $book->is_sold) {
                $book->stock_quantity = 0;
            }
        });
    }

    /**
     * Returns true when the URL points to an actual cover image,
     * not a generic placeholder service.
     */
    private static function isRealCoverUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        $placeholders = [
            'via.placeholder.com',
            'placeholder.com',
            'placehold.co',
            'placehold.it',
            'placekitten.com',
            'dummyimage.com',
        ];

        $lower = strtolower($url);
        foreach ($placeholders as $host) {
            if (str_contains($lower, $host)) {
                return false;
            }
        }

        return true;
    }

    public function isUsed(): bool
    {
        return strtolower((string) ($this->condition ?? 'new')) === 'used';
    }

    public function isPurchasable(): bool
    {
        return ($this->is_visible ?? true)
            && ! ($this->is_sold ?? false)
            && (int) ($this->stock_quantity ?? 0) > 0;
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

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class);
    }
}
