<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        $books = DB::connection('mongodb')->getCollection('books');

        // Denormalize cover presence so catalog filters can use an index.
        $books->updateMany(
            [
                '$or' => [
                    ['cover_image' => ['$nin' => [null, '']]],
                    ['cover_image_thumb' => ['$nin' => [null, '']]],
                ],
            ],
            ['$set' => ['has_cover' => true]]
        );
        $books->updateMany(
            [
                '$and' => [
                    ['$or' => [
                        ['cover_image' => null],
                        ['cover_image' => ''],
                        ['cover_image' => ['$exists' => false]],
                    ]],
                    ['$or' => [
                        ['cover_image_thumb' => null],
                        ['cover_image_thumb' => ''],
                        ['cover_image_thumb' => ['$exists' => false]],
                    ]],
                ],
            ],
            ['$set' => ['has_cover' => false]]
        );

        Schema::connection('mongodb')->table('books', function (Blueprint $collection) {
            $collection->index('has_cover', 'books_has_cover_idx');
            // Matches public catalog: in stock + has cover, newest first.
            $collection->index(
                ['has_cover' => 1, 'stock_quantity' => 1, 'created_at' => -1, '_id' => -1],
                'books_catalog_idx'
            );
            $collection->index(['title' => 'text', 'isbn' => 'text'], 'books_text_search_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->table('books', function (Blueprint $collection) {
            $collection->dropIndex('books_has_cover_idx');
            $collection->dropIndex('books_catalog_idx');
            $collection->dropIndex('books_text_search_idx');
        });
    }
};
