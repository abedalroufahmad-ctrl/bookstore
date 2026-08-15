<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mongodb')->table('books', function ($collection) {
            $collection->index(['warehouse_id' => 1, 'isbn' => 1], 'books_warehouse_isbn_idx');
            $collection->index(['condition' => 1, 'is_visible' => 1, 'is_sold' => 1, 'stock_quantity' => 1], 'books_condition_visibility_idx');
            $collection->index(['is_visible' => 1, 'is_sold' => 1, 'has_cover' => 1, 'stock_quantity' => 1], 'books_public_catalog_visibility_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->table('books', function ($collection) {
            $collection->dropIndex('books_warehouse_isbn_idx');
            $collection->dropIndex('books_condition_visibility_idx');
            $collection->dropIndex('books_public_catalog_visibility_idx');
        });
    }
};
