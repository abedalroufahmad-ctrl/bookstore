<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        Schema::connection('mongodb')->create('carts', function (Blueprint $collection) {
            $collection->index('customer_id');
            $collection->index('status');
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->drop('carts');
    }
};
