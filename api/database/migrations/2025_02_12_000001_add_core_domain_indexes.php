<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        Schema::connection('mongodb')->table('warehouses', function (Blueprint $collection) {
            $collection->index('city');
            $collection->index('country');
        });

        Schema::connection('mongodb')->table('books', function (Blueprint $collection) {
            $collection->index('stock_quantity');
            $collection->index('price');
            $collection->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->table('warehouses', function (Blueprint $collection) {
            $collection->dropIndex(['city']);
            $collection->dropIndex(['country']);
        });

        Schema::connection('mongodb')->table('books', function (Blueprint $collection) {
            $collection->dropIndex(['stock_quantity']);
            $collection->dropIndex(['price']);
            $collection->dropIndex(['created_at' => -1]);
        });
    }
};
