<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->json('payment_info')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('publishers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->json('settings')->nullable();
            $table->decimal('commission_owed', 14, 2)->default(0);
            $table->decimal('commission_accrued_total', 14, 2)->default(0);
            $table->decimal('commission_paid_total', 14, 2)->default(0);
            $table->json('commission_balances')->nullable();
            $table->timestamps();
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->json('location')->nullable();
            $table->json('payment_methods')->nullable();
            $table->json('shipping_options')->nullable();
            $table->string('manager_id')->nullable()->index();
            $table->string('publisher_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->string('role')->index();
            $table->string('warehouse_id')->nullable()->index();
            $table->json('warehouse_ids')->nullable();
            $table->string('publisher_id')->nullable()->index();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('authors', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->text('biography')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->date('date_of_death')->nullable();
            $table->string('photo')->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('dewey_code')->nullable()->index();
            $table->string('subject_title_en')->nullable();
            $table->string('subject_title_ar')->nullable();
            $table->string('subject_number')->nullable();
            $table->timestamps();
        });

        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title')->index();
            $table->json('author_ids')->nullable();
            $table->string('category_id')->nullable()->index();
            $table->string('size')->nullable();
            $table->decimal('weight', 10, 3)->nullable();
            $table->text('cover_image')->nullable();
            $table->text('cover_image_thumb')->nullable();
            $table->boolean('has_cover')->default(false)->index();
            $table->text('description')->nullable();
            $table->decimal('price', 14, 2)->default(0);
            $table->json('prices')->nullable();
            $table->unsignedInteger('pages')->nullable();
            $table->string('isbn')->nullable()->index();
            $table->unsignedSmallInteger('publish_year')->nullable();
            $table->unsignedInteger('edition_number')->nullable();
            $table->string('binding_type')->nullable();
            $table->string('paper_type')->nullable();
            $table->string('publisher_id')->nullable()->index();
            $table->json('publisher_ids')->nullable();
            $table->string('warehouse_id')->nullable()->index();
            $table->integer('stock_quantity')->default(0);
            $table->decimal('discount_percent', 8, 2)->nullable();
            $table->string('condition')->default('new')->index();
            $table->boolean('is_visible')->default(true)->index();
            $table->boolean('is_sold')->default(false)->index();
            $table->timestamps();
            $table->index(['has_cover', 'stock_quantity', 'is_visible', 'is_sold'], 'books_catalog_idx');
        });

        Schema::create('author_book', function (Blueprint $table) {
            $table->string('author_id')->index();
            $table->string('book_id')->index();
            $table->primary(['author_id', 'book_id']);
        });

        Schema::create('book_publisher', function (Blueprint $table) {
            $table->string('publisher_id')->index();
            $table->string('book_id')->index();
            $table->primary(['publisher_id', 'book_id']);
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->string('customer_id')->index();
            $table->json('items')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('customer_id')->nullable()->index();
            $table->string('customer_name')->nullable();
            $table->boolean('is_direct_sale')->default(false)->index();
            $table->string('employee_id')->nullable()->index();
            $table->string('warehouse_id')->nullable()->index();
            $table->json('items')->nullable();
            $table->string('status')->index();
            $table->boolean('stock_reserved')->default(false);
            $table->decimal('books_subtotal', 14, 2)->nullable();
            $table->decimal('shipping_fee', 14, 2)->nullable();
            $table->string('shipping_method')->nullable();
            $table->decimal('total', 14, 2)->default(0);
            $table->string('currency_code', 8)->default('USD');
            $table->json('shipping_address')->nullable();
            $table->json('payment_info')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->nullable()->index();
            $table->string('stripe_checkout_session_id')->nullable();
            $table->unsignedBigInteger('stripe_checkout_session_expires_at')->nullable();
            $table->decimal('platform_commission_percent', 8, 2)->nullable();
            $table->decimal('platform_commission_amount', 14, 2)->nullable();
            $table->decimal('publisher_payout_amount', 14, 2)->nullable();
            $table->string('payout_paypal_email')->nullable();
            $table->string('payout_paypal_merchant_id')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->nullable()->index();
            $table->string('user_id')->nullable()->index();
            $table->decimal('amount', 14, 2)->nullable();
            $table->string('currency', 8)->nullable();
            $table->string('method')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->nullable();
            $table->string('status')->nullable();
            $table->string('transaction_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('publisher_commission_entries', function (Blueprint $table) {
            $table->id();
            $table->string('publisher_id')->index();
            $table->string('order_id')->nullable()->index();
            $table->string('type')->index();
            $table->decimal('amount', 14, 2);
            $table->string('currency_code', 8)->default('USD');
            $table->text('note')->nullable();
            $table->string('reference')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->timestamps();
        });

        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('code', 8)->nullable()->index();
            $table->json('translations')->nullable();
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('country_id')->index();
            $table->json('translations')->nullable();
            $table->timestamps();
        });

        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique();
            $table->string('symbol')->nullable();
            $table->string('name')->nullable();
            $table->string('nameArabic')->nullable();
            $table->decimal('exchangeRate', 16, 6)->default(1);
            $table->boolean('isActive')->default(true);
            $table->boolean('isDefault')->default(false);
            $table->string('countryCode', 8)->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('countries');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('publisher_commission_entries');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('book_publisher');
        Schema::dropIfExists('author_book');
        Schema::dropIfExists('books');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('authors');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('publishers');
        Schema::dropIfExists('customers');
    }
};
