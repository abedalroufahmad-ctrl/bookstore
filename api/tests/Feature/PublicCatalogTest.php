<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Warehouse;
use Tests\TestCase;

class PublicCatalogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $warehouse = Warehouse::first() ?? Warehouse::create([
            'name' => 'Test Warehouse',
            'address' => '123 Test St',
            'country' => 'USA',
            'city' => 'Test City',
            'phone' => '+1234567890',
            'email' => 'warehouse@test.test',
        ]);

        $category = Category::first() ?? Category::create([
            'dewey_code' => '001',
            'subject_title' => 'Test Category',
        ]);

        $author = Author::first() ?? Author::create([
            'name' => 'Test Author',
        ]);

        $this->book = Book::first() ?? Book::create([
            'title' => 'Test Book',
            'author_ids' => [$author->getKey()],
            'category_id' => $category->getKey(),
            'price' => 29.99,
            'isbn' => '978-0-000-00000-0',
            'warehouse_id' => $warehouse->getKey(),
            'stock_quantity' => 10,
        ]);
    }

    public function test_public_books_list_returns_success(): void
    {
        $response = $this->getJson('/api/v1/books');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'data',
                'current_page',
                'links',
            ],
        ]);
    }

    public function test_public_book_show_returns_book(): void
    {
        $response = $this->getJson('/api/v1/books/' . $this->book->getKey());

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'Test Book');
    }

    public function test_public_books_no_auth_required(): void
    {
        $response = $this->getJson('/api/v1/books');

        $response->assertStatus(200);
    }

    public function test_public_categories_list_returns_success(): void
    {
        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(200);
    }

    public function test_public_authors_list_returns_success(): void
    {
        $response = $this->getJson('/api/v1/authors');

        $response->assertStatus(200);
    }
}
