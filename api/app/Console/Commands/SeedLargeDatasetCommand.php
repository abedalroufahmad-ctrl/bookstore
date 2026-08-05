<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Publisher;
use App\Models\Warehouse;
use MongoDB\BSON\ObjectId;
use Faker\Factory as Faker;
use Illuminate\Support\Carbon;

class SeedLargeDatasetCommand extends Command
{
    protected $signature = 'db:seed-large {--books=100000} {--authors=100000} {--chunk=5000}';
    protected $description = 'Quickly seed a massive amount of books and authors for load testing';

    public function handle()
    {
        $bookCount = (int) $this->option('books');
        $authorCount = (int) $this->option('authors');
        $chunkSize = (int) $this->option('chunk');

        $this->info("Preparing to insert $authorCount authors and $bookCount books...");

        $faker = Faker::create();

        // Ensure we have at least one Category, Publisher, Warehouse
        $category = Category::first() ?? Category::create(['subject_title_en' => 'Test Category', 'subject_title_ar' => 'Test Category', 'dewey_code' => '000']);
        $publisher = Publisher::first() ?? Publisher::create(['name' => 'Test Publisher', 'email' => 'pub@test.test']);
        $warehouse = Warehouse::first() ?? Warehouse::create(['name' => 'Test Warehouse', 'publisher_id' => $publisher->id]);

        $categoryIds = Category::pluck('_id')->toArray();
        $publisherIds = Publisher::pluck('_id')->toArray();
        $warehouseIds = Warehouse::pluck('_id')->toArray();

        // 1. Seed Authors
        $this->info('Seeding Authors...');
        $authorBar = $this->output->createProgressBar($authorCount);
        $authorIds = [];
        
        $authorsData = [];
        for ($i = 0; $i < $authorCount; $i++) {
            $oid = (string) new ObjectId();
            $authorIds[] = $oid;

            $authorsData[] = [
                '_id' => new ObjectId($oid),
                'name' => $faker->name,
                'biography' => $faker->sentence(10),
                'date_of_birth' => $faker->date('Y-m-d', '2000-01-01'),
                'created_at' => new \MongoDB\BSON\UTCDateTime(now()),
                'updated_at' => new \MongoDB\BSON\UTCDateTime(now()),
            ];

            if (count($authorsData) >= $chunkSize) {
                Author::insert($authorsData);
                $authorBar->advance(count($authorsData));
                $authorsData = [];
            }
        }
        if (!empty($authorsData)) {
            Author::insert($authorsData);
            $authorBar->advance(count($authorsData));
        }
        $authorBar->finish();
        $this->newLine();

        // 2. Seed Books
        $this->info('Seeding Books...');
        $bookBar = $this->output->createProgressBar($bookCount);
        
        $booksData = [];
        for ($i = 0; $i < $bookCount; $i++) {
            // Pick 1 to 3 random authors
            $numAuthors = rand(1, 3);
            $randAuthorIds = [];
            for ($a = 0; $a < $numAuthors; $a++) {
                $randAuthorIds[] = $authorIds[array_rand($authorIds)];
            }

            $booksData[] = [
                '_id' => new ObjectId(),
                'title' => ucwords($faker->words(rand(2, 5), true)),
                'author_ids' => array_unique($randAuthorIds),
                'category_id' => $categoryIds[array_rand($categoryIds)],
                'publisher_id' => $publisherIds[array_rand($publisherIds)],
                'warehouse_id' => $warehouseIds[array_rand($warehouseIds)],
                'price' => $faker->randomFloat(2, 10, 200),
                'stock_quantity' => rand(0, 500),
                'discount_percent' => rand(0, 100) > 80 ? rand(5, 50) : 0,
                'pages' => rand(100, 1000),
                'isbn' => $faker->isbn13(),
                'publish_year' => rand(1900, 2026),
                'created_at' => new \MongoDB\BSON\UTCDateTime(now()),
                'updated_at' => new \MongoDB\BSON\UTCDateTime(now()),
            ];

            if (count($booksData) >= $chunkSize) {
                Book::insert($booksData);
                $bookBar->advance(count($booksData));
                $booksData = [];
            }
        }
        if (!empty($booksData)) {
            Book::insert($booksData);
            $bookBar->advance(count($booksData));
        }
        $bookBar->finish();
        $this->newLine();

        $this->info('Done! Seeded successfully.');
    }
}
