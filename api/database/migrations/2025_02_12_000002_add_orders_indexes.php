<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        Schema::connection('mongodb')->table('orders', function (Blueprint $collection) {
            $collection->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->table('orders', function (Blueprint $collection) {
            $collection->dropIndex(['created_at' => -1]);
        });
    }
};
