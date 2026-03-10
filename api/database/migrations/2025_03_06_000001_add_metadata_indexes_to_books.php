<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        Schema::connection('mongodb')->table('books', function (Blueprint $collection) {
            // Index for category filtering
            $collection->index('category_id');
            
            // Multikey index for author filtering (author_ids is an array)
            $collection->index('author_ids');
            
            // Index for sorting by discount (Sale/Special offers)
            $collection->index(['discount_percent' => -1]);
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->table('books', function (Blueprint $collection) {
            $collection->dropIndex(['category_id']);
            $collection->dropIndex(['author_ids']);
            $collection->dropIndex(['discount_percent' => -1]);
        });
    }
};
