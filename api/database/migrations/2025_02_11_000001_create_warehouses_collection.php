<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        Schema::connection('mongodb')->create('warehouses', function (Blueprint $collection) {
            $collection->index('name');
            $collection->index('email');
            // 2dsphere index removed - add when geo queries needed; location stored as object
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->drop('warehouses');
    }
};
