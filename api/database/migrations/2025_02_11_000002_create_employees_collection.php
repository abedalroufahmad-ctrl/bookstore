<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        Schema::connection('mongodb')->create('employees', function (Blueprint $collection) {
            $collection->unique('email', options: ['name' => 'employees_email_unique']);
            $collection->index('warehouse_id');
            $collection->index('role');
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->drop('employees');
    }
};
