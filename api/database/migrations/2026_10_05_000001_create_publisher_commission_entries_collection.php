<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        Schema::connection('mongodb')->create('publisher_commission_entries', function (Blueprint $collection) {
            $collection->index('publisher_id');
            $collection->index('type');
            $collection->index('created_at');
            // One sale accrual per order; settlements omit order_id and are excluded by sparse.
            $collection->unique('order_id', null, ['sparse' => true]);
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->drop('publisher_commission_entries');
    }
};
