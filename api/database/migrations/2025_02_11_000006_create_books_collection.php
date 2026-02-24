<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        Schema::connection('mongodb')->create('books', function (Blueprint $collection) {
            $collection->unique('isbn', options: ['name' => 'books_isbn_unique']);
            $collection->index('title');
            $collection->index('category_id');
            $collection->index('warehouse_id');
            $collection->index('author_ids');
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->drop('books');
    }
};
