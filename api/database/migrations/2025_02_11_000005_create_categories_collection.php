<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        Schema::connection('mongodb')->create('categories', function (Blueprint $collection) {
            $collection->unique('dewey_code', options: ['name' => 'categories_dewey_code_unique']);
            $collection->index('subject_title');
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->drop('categories');
    }
};
