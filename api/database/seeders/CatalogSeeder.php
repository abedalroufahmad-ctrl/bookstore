<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $warehouse = Warehouse::first();
        if (! $warehouse) {
            $warehouse = Warehouse::create([
                'name' => 'Main Warehouse',
                'address' => '123 Storage St',
                'country' => 'USA',
                'city' => 'New York',
            ]);
        }

        $authors = [
            Author::firstOrCreate(['name' => 'J.K. Rowling']),
            Author::firstOrCreate(['name' => 'George Orwell']),
            Author::firstOrCreate(['name' => 'Jane Austen']),
        ];

        $categories = [
            Category::firstOrCreate(
                ['dewey_code' => '823'],
                ['dewey_code' => '823', 'subject_title' => 'Fiction', 'subject_number' => '823']
            ),
            Category::firstOrCreate(
                ['dewey_code' => '813'],
                ['dewey_code' => '813', 'subject_title' => 'American Fiction', 'subject_number' => '813']
            ),
        ];

        $books = [
            [
                'title' => 'Harry Potter and the Philosopher\'s Stone',
                'isbn' => '978-0747532699',
                'price' => 12.99,
                'stock_quantity' => 50,
                'author_ids' => [(string) $authors[0]->getKey()],
                'category_id' => (string) $categories[0]->getKey(),
                'warehouse_id' => (string) $warehouse->getKey(),
                'description' => 'The first book in the Harry Potter series.',
                'pages' => 223,
                'publish_year' => 1997,
            ],
            [
                'title' => '1984',
                'isbn' => '978-0451524935',
                'price' => 9.99,
                'stock_quantity' => 30,
                'author_ids' => [(string) $authors[1]->getKey()],
                'category_id' => (string) $categories[0]->getKey(),
                'warehouse_id' => (string) $warehouse->getKey(),
                'description' => 'A dystopian social science fiction novel.',
                'pages' => 328,
                'publish_year' => 1949,
            ],
            [
                'title' => 'Pride and Prejudice',
                'isbn' => '978-0141439518',
                'price' => 8.99,
                'stock_quantity' => 25,
                'author_ids' => [(string) $authors[2]->getKey()],
                'category_id' => (string) $categories[0]->getKey(),
                'warehouse_id' => (string) $warehouse->getKey(),
                'description' => 'A romantic novel of manners.',
                'pages' => 480,
                'publish_year' => 1813,
            ],
        ];

        foreach ($books as $data) {
            if (! Book::where('isbn', $data['isbn'])->exists()) {
                Book::create($data);
            }
        }

        Cache::flush();
    }
}
