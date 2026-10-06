<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        Schema::connection('mongodb')->create('customers', function (Blueprint $collection) {
            $collection->unique('email', options: ['name' => 'customers_email_unique']);
            $collection->index('country');
            $collection->index('city');
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->drop('customers');
    }
};
