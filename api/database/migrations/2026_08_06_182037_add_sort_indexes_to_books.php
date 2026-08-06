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
            // Compound index for sorting exactly as queried
            $collection->index(['created_at' => -1, '_id' => -1], 'books_created_id_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->table('books', function (Blueprint $collection) {
            $collection->dropIndex('books_created_id_idx');
        });
    }
};
