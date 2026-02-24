<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        Schema::connection('mongodb')->create('authors', function (Blueprint $collection) {
            $collection->index('name');
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->drop('authors');
    }
};
