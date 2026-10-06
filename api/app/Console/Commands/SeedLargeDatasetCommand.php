<?php

namespace App\Console\Commands;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Publisher;
use App\Models\Warehouse;
use Faker\Factory as Faker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class SeedLargeDatasetCommand extends Command
{
    protected $signature = 'db:seed-large
        {--books=1000000 : Number of books to insert (ignored if --target-books is set)}
        {--authors=0 : New authors to insert (0 = reuse existing authors only)}
        {--chunk=5000 : Bulk insert chunk size}
        {--target-books= : Insert only enough books to reach this total count}
        {--in-stock-ratio=0.95 : Fraction of books with stock_quantity > 0}';

    protected $description = 'Bulk-seed books (with covers) and optional authors for load testing';

    public function handle(): int
    {
        $chunkSize = max(100, (int) $this->option('chunk'));
        $authorCount = max(0, (int) $this->option('authors'));
        $inStockRatio = min(1.0, max(0.0, (float) $this->option('in-stock-ratio')));

        $existingBooks = (int) Book::count();
        $targetBooks = $this->option('target-books');
        if ($targetBooks !== null && $targetBooks !== '') {
            $target = max(0, (int) $targetBooks);
            $bookCount = max(0, $target - $existingBooks);
            $this->info("Existing books: {$existingBooks}. Target: {$target}. Will insert: {$bookCount}.");
        } else {
            $bookCount = max(0, (int) $this->option('books'));
        }

        if ($bookCount === 0 && $authorCount === 0) {
            $this->warn('Nothing to insert.');

            return self::SUCCESS;
        }

        $this->info("Preparing: {$authorCount} authors, {$bookCount} books (chunk={$chunkSize})...");

        $faker = Faker::create();
        $now = new UTCDateTime(now());

        Category::first() ?? Category::create([
            'subject_title_en' => 'Test Category',
            'subject_title_ar' => 'Test Category',
            'dewey_code' => '000',
        ]);
        $publisher = Publisher::first() ?? Publisher::create([
            'name' => 'Test Publisher',
            'email' => 'pub@test.test',
        ]);
        Warehouse::first() ?? Warehouse::create([
            'name' => 'Test Warehouse',
            'publisher_id' => $publisher->id,
        ]);

        $categoryIds = Category::pluck('_id')->map(fn ($id) => (string) $id)->all();
        $publisherIds = Publisher::pluck('_id')->map(fn ($id) => (string) $id)->all();
        $warehouseIds = Warehouse::pluck('_id')->map(fn ($id) => (string) $id)->all();

        if ($categoryIds === [] || $publisherIds === [] || $warehouseIds === []) {
            $this->error('Need at least one category, publisher, and warehouse.');

            return self::FAILURE;
        }

        $authorIds = Author::pluck('_id')->map(fn ($id) => (string) $id)->all();
        $authorsCollection = DB::connection('mongodb')->getCollection('authors');
        $booksCollection = DB::connection('mongodb')->getCollection('books');

        if ($authorCount > 0) {
            $this->info('Seeding authors...');
            $bar = $this->output->createProgressBar($authorCount);
            $batch = [];
            for ($i = 0; $i < $authorCount; $i++) {
                $oid = new ObjectId;
                $authorIds[] = (string) $oid;
                $batch[] = [
                    '_id' => $oid,
                    'name' => $faker->name(),
                    'biography' => $faker->sentence(10),
                    'date_of_birth' => $faker->date('Y-m-d', '2000-01-01'),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                if (count($batch) >= $chunkSize) {
                    $authorsCollection->insertMany($batch, ['ordered' => false]);
                    $bar->advance(count($batch));
                    $batch = [];
                }
            }
            if ($batch !== []) {
                $authorsCollection->insertMany($batch, ['ordered' => false]);
                $bar->advance(count($batch));
            }
            $bar->finish();
            $this->newLine();
        }

        if ($authorIds === []) {
            $this->error('No authors available. Pass --authors=N or seed authors first.');

            return self::FAILURE;
        }

        $authorPoolSize = count($authorIds);
        $this->info("Author pool size: {$authorPoolSize}");

        if ($bookCount > 0) {
            $this->info('Seeding books with covers...');
            $bar = $this->output->createProgressBar($bookCount);
            $batch = [];
            $catCount = count($categoryIds);
            $pubCount = count($publisherIds);
            $whCount = count($warehouseIds);

            for ($i = 0; $i < $bookCount; $i++) {
                $oid = new ObjectId;
                $seed = (string) $oid;
                $numAuthors = random_int(1, 3);
                $picked = [];
                for ($a = 0; $a < $numAuthors; $a++) {
                    $picked[] = $authorIds[random_int(0, $authorPoolSize - 1)];
                }

                $inStock = (mt_rand() / mt_getrandmax()) <= $inStockRatio;

                $batch[] = [
                    '_id' => $oid,
                    'title' => ucwords($faker->words(random_int(2, 5), true)),
                    'author_ids' => array_values(array_unique($picked)),
                    'category_id' => $categoryIds[random_int(0, $catCount - 1)],
                    'publisher_id' => $publisherIds[random_int(0, $pubCount - 1)],
                    'warehouse_id' => $warehouseIds[random_int(0, $whCount - 1)],
                    'price' => round(mt_rand(1000, 20000) / 100, 2),
                    'stock_quantity' => $inStock ? random_int(1, 500) : 0,
                    'discount_percent' => random_int(1, 100) > 80 ? random_int(5, 50) : 0,
                    'pages' => random_int(100, 1000),
                    'isbn' => $faker->unique()->isbn13(),
                    'publish_year' => random_int(1900, 2026),
                    // Stable, unique cover URLs (picsum) so catalog has_cover filter passes.
                    'cover_image' => "https://picsum.photos/seed/{$seed}/600/800",
                    'cover_image_thumb' => "https://picsum.photos/seed/{$seed}/300/400",
                    'has_cover' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($batch) >= $chunkSize) {
                    $booksCollection->insertMany($batch, ['ordered' => false]);
                    $bar->advance(count($batch));
                    $batch = [];
                    // Avoid Faker unique() memory growth on huge runs
                    if (($i + 1) % 50000 === 0) {
                        $faker->unique(true);
                    }
                }
            }

            if ($batch !== []) {
                $booksCollection->insertMany($batch, ['ordered' => false]);
                $bar->advance(count($batch));
            }
            $bar->finish();
            $this->newLine();
        }

        $finalBooks = (int) Book::count();
        $finalAuthors = (int) Author::count();
        $this->info("Done. Books total: {$finalBooks}. Authors total: {$finalAuthors}.");
        $this->info('All new books have cover_image + cover_image_thumb + has_cover=true.');

        return self::SUCCESS;
    }
}
