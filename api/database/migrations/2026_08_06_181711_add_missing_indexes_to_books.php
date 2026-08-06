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
            $collection->index('publisher_id');
            $collection->index('warehouse_id');
            // Index for default sorting
            $collection->index(['created_at' => -1]);
        });
        
        Schema::connection('mongodb')->table('authors', function (Blueprint $collection) {
            // Authors need an index on name for sorting/searching
            $collection->index('name');
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->table('books', function (Blueprint $collection) {
            $collection->dropIndex(['publisher_id']);
            $collection->dropIndex(['warehouse_id']);
            $collection->dropIndex(['created_at' => -1]);
        });
        
        Schema::connection('mongodb')->table('authors', function (Blueprint $collection) {
            $collection->dropIndex(['name']);
        });
    }
};
