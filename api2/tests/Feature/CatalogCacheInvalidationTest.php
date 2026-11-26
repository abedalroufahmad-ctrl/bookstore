<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\Publisher;
use App\Support\CatalogCache;
use Illuminate\Support\Facades\Cache;

class CatalogCacheInvalidationTest extends MongoFeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['catalog.cache_enabled' => true]);
    }

    public function test_category_rename_is_visible_immediately(): void
    {
        $category = Category::create(['dewey_code' => '800', 'subject_title_en' => 'Old Name']);

        $this->getJson('/api/v1/categories')->assertOk()->assertSee('Old Name');

        $category->update(['subject_title_en' => 'New Name']);

        $this->getJson('/api/v1/categories')->assertOk()->assertSee('New Name')->assertDontSee('Old Name');
    }

    public function test_publisher_rename_refreshes_cached_book_listing(): void
    {
        $publisher = Publisher::create(['name' => 'Before Press']);
        Book::create([
            'title' => 'Cached Book',
            'price' => 5,
            'stock_quantity' => 2,
            'condition' => 'new',
            'is_visible' => true,
            'is_sold' => false,
            'cover_image' => 'https://covers.example.test/book.jpg',
            'publisher_id' => (string) $publisher->_id,
            'publisher_ids' => [(string) $publisher->_id],
        ]);

        $this->getJson('/api/v1/books')->assertOk()->assertSee('Before Press');

        $publisher->update(['name' => 'After Press']);

        $this->getJson('/api/v1/books')->assertOk()->assertSee('After Press');
    }

    public function test_bump_does_not_flush_unrelated_cache_entries(): void
    {
        Cache::put('unrelated_key', 'keep-me', 60);

        CatalogCache::bump();

        $this->assertSame('keep-me', Cache::get('unrelated_key'));
    }
}
