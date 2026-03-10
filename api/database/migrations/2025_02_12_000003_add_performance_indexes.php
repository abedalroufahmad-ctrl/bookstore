<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        // Carts: compound index for findActiveByCustomer (customer_id + status)
        Schema::connection('mongodb')->table('carts', function (Blueprint $collection) {
            $collection->index(
                ['customer_id' => 1, 'status' => 1],
                'carts_customer_status_idx'
            );
        });

        // Orders: compound for customer order list (customer_id + created_at)
        Schema::connection('mongodb')->table('orders', function (Blueprint $collection) {
            $collection->index(
                ['customer_id' => 1, 'created_at' => -1],
                'orders_customer_created_idx'
            );
            $collection->index(
                ['status' => 1, 'created_at' => -1],
                'orders_status_created_idx'
            );
        });

        // Books: compound for public catalog (stock_quantity + created_at)
        Schema::connection('mongodb')->table('books', function (Blueprint $collection) {
            $collection->index(
                ['stock_quantity' => 1, 'created_at' => -1],
                'books_stock_created_idx'
            );
        });

        // Employees: index for admin list by name
        Schema::connection('mongodb')->table('employees', function (Blueprint $collection) {
            $collection->index('name');
        });

        // Customers: index for admin order search by name (email has unique index)
        Schema::connection('mongodb')->table('customers', function (Blueprint $collection) {
            $collection->index('name');
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->table('carts', function (Blueprint $collection) {
            $collection->dropIndex('carts_customer_status_idx');
        });

        Schema::connection('mongodb')->table('orders', function (Blueprint $collection) {
            $collection->dropIndex('orders_customer_created_idx');
            $collection->dropIndex('orders_status_created_idx');
        });

        Schema::connection('mongodb')->table('books', function (Blueprint $collection) {
            $collection->dropIndex('books_stock_created_idx');
        });

        Schema::connection('mongodb')->table('employees', function (Blueprint $collection) {
            $collection->dropIndex(['name']);
        });

        Schema::connection('mongodb')->table('customers', function (Blueprint $collection) {
            $collection->dropIndex(['name']);
        });
    }
};
