<?php

use App\Http\Controllers\Api\Admin\AdminOrderController;
use App\Http\Controllers\Api\Admin\AuthorController;
use App\Http\Controllers\Api\Admin\BookController;
use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\CountryController;
use App\Http\Controllers\Api\Admin\CustomerController;
use App\Http\Controllers\Api\Admin\EmployeeController;
use App\Http\Controllers\Api\Admin\PublisherController;
use App\Http\Controllers\Api\Admin\SettingController;
use App\Http\Controllers\Api\Admin\UploadAuthorPhotoController;
use App\Http\Controllers\Api\Admin\UploadCoverController;
use App\Http\Controllers\Api\Admin\WarehouseController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CustomerAuthController;
use App\Http\Controllers\Api\EmployeeAuthController;
use App\Http\Controllers\Api\EmployeeOrderController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PayPalController;
use App\Http\Controllers\Api\PublicAuthorController;
use App\Http\Controllers\Api\PublicBookController;
use App\Http\Controllers\Api\PublicCategoryController;
use App\Http\Controllers\Api\PublicPublisherController;
use App\Http\Controllers\Api\PublicWarehouseController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:60,1')->prefix('v1')->group(function () {
    // Payment webhooks (no auth; signature verified in controller — fail closed)
    Route::post('webhooks/stripe', [WebhookController::class, 'stripe']);
    Route::post('webhooks/paypal', [WebhookController::class, 'paypal']);

    Route::get('paypal/complete', [PayPalController::class, 'complete']);

    // Public Catalog (no auth)
    Route::get('books', [PublicBookController::class, 'index']);
    Route::get('books/{id}', [PublicBookController::class, 'show']);
    Route::get('categories', [PublicCategoryController::class, 'index']);
    Route::get('categories/{id}', [PublicCategoryController::class, 'show']);
    Route::get('warehouses', [PublicWarehouseController::class, 'index']);
    Route::get('warehouses/{id}', [PublicWarehouseController::class, 'show']);
    Route::get('publishers/{id}', [PublicPublisherController::class, 'show']);
    Route::get('authors', [PublicAuthorController::class, 'index']);
    Route::get('authors/{id}', [PublicAuthorController::class, 'show']);
    Route::get('settings', [SettingController::class, 'publicIndex']);

    // Admin Management — role-split (never grant full admin to shipping/review/accounting alone)
    Route::prefix('admin')->middleware(['auth:employee', 'restrict.warehouse_manager', 'restrict.publisher_manager'])->group(function () {
        // Catalog: managers + publisher managers (scoped by restrict middleware)
        Route::middleware('role:manager,publisher_manager')->group(function () {
            Route::post('upload-cover', UploadCoverController::class);
            Route::post('upload-author-photo', UploadAuthorPhotoController::class);

            Route::get('books', [BookController::class, 'index']);
            Route::post('books', [BookController::class, 'store']);
            Route::get('books/{id}', [BookController::class, 'show']);
            Route::put('books/{id}', [BookController::class, 'update']);
            Route::delete('books/{id}', [BookController::class, 'destroy']);

            Route::get('authors', [AuthorController::class, 'index']);
            Route::post('authors', [AuthorController::class, 'store']);
            Route::get('authors/{id}', [AuthorController::class, 'show']);
            Route::put('authors/{id}', [AuthorController::class, 'update']);
            Route::delete('authors/{id}', [AuthorController::class, 'destroy']);

            Route::get('categories', [CategoryController::class, 'index']);
            Route::get('categories/{id}', [CategoryController::class, 'show']);

            Route::get('publishers', [PublisherController::class, 'index']);
            Route::get('publishers/{id}', [PublisherController::class, 'show']);
            Route::get('publishers/{id}/settings', [PublisherController::class, 'getSettings']);
            Route::put('publishers/{id}/settings', [PublisherController::class, 'updateSettings']);

            Route::get('warehouses', [WarehouseController::class, 'index']);
            Route::get('warehouses/{id}', [WarehouseController::class, 'show']);
            Route::put('warehouses/{id}', [WarehouseController::class, 'update']);

            Route::get('settings', [SettingController::class, 'index']);
        });

        // Categories write + publishers CRUD + warehouse create/delete: managers only
        Route::middleware('role:manager')->group(function () {
            Route::post('categories', [CategoryController::class, 'store']);
            Route::put('categories/{id}', [CategoryController::class, 'update']);
            Route::delete('categories/{id}', [CategoryController::class, 'destroy']);

            Route::post('publishers', [PublisherController::class, 'store']);
            Route::put('publishers/{id}', [PublisherController::class, 'update']);
            Route::delete('publishers/{id}', [PublisherController::class, 'destroy']);

            Route::post('warehouses', [WarehouseController::class, 'store']);
            Route::delete('warehouses/{id}', [WarehouseController::class, 'destroy']);

            Route::get('employees', [EmployeeController::class, 'index']);
            Route::post('employees', [EmployeeController::class, 'store']);
            Route::get('employees/{id}', [EmployeeController::class, 'show']);
            Route::put('employees/{id}', [EmployeeController::class, 'update']);

            Route::get('customers', [CustomerController::class, 'index']);
            Route::get('customers/{id}', [CustomerController::class, 'show']);
            Route::put('customers/{id}', [CustomerController::class, 'update']);
            Route::delete('customers/{id}', [CustomerController::class, 'destroy']);
            Route::post('customers/{id}/convert-to-employee', [CustomerController::class, 'convertToEmployee']);

            Route::put('settings', [SettingController::class, 'update']);

            Route::get('countries', [CountryController::class, 'index']);
            Route::post('countries/sync-from-network', [CountryController::class, 'syncFromNetwork']);
            Route::post('countries/sync-cities-from-dataset', [CountryController::class, 'syncCitiesFromDataset']);
            Route::get('countries/{id}', [CountryController::class, 'show']);
        });

        // Warehouse managers: staff + warehouses they manage (further scoped in controllers/middleware)
        Route::middleware('role:warehouse_manager')->group(function () {
            Route::get('warehouses', [WarehouseController::class, 'index']);
            Route::get('warehouses/{id}', [WarehouseController::class, 'show']);
            Route::put('warehouses/{id}', [WarehouseController::class, 'update']);

            Route::get('employees', [EmployeeController::class, 'index']);
            Route::post('employees', [EmployeeController::class, 'store']);
            Route::get('employees/{id}', [EmployeeController::class, 'show']);
            Route::put('employees/{id}', [EmployeeController::class, 'update']);

            Route::get('settings', [SettingController::class, 'index']);
        });

        // Orders: order-management roles only
        Route::middleware('role:manager,shipping,accounting,warehouse_manager')->group(function () {
            Route::get('orders', [AdminOrderController::class, 'index']);
            Route::get('orders/{id}', [AdminOrderController::class, 'show']);
            Route::patch('orders/{id}/status', [AdminOrderController::class, 'updateStatus']);
            Route::post('orders/{id}/warehouse-quote', [AdminOrderController::class, 'submitWarehouseQuote']);
            Route::post('orders/{id}/assign', [AdminOrderController::class, 'assign']);
        });
    });

    // Employee Auth
    Route::prefix('employees')->group(function () {
        Route::post('login', [EmployeeAuthController::class, 'login'])->middleware('throttle:5,1');

        Route::middleware(['auth:employee'])->group(function () {
            Route::post('logout', [EmployeeAuthController::class, 'logout']);
            Route::post('refresh', [EmployeeAuthController::class, 'refresh']);
            Route::get('me', [EmployeeAuthController::class, 'me']);

            Route::middleware('role:manager,shipping,accounting,warehouse_manager')->group(function () {
                Route::get('orders', [EmployeeOrderController::class, 'index']);
                Route::get('orders/{id}', [EmployeeOrderController::class, 'show']);
                Route::patch('orders/{id}/status', [EmployeeOrderController::class, 'updateStatus']);
                Route::post('orders/{id}/warehouse-quote', [EmployeeOrderController::class, 'submitWarehouseQuote']);
            });
        });
    });

    // Customer Auth
    Route::prefix('customers')->group(function () {
        Route::post('register', [CustomerAuthController::class, 'register'])->middleware('throttle:10,1');
        Route::post('login', [CustomerAuthController::class, 'login'])->middleware('throttle:5,1');

        Route::middleware(['auth:customer'])->group(function () {
            Route::post('logout', [CustomerAuthController::class, 'logout']);
            Route::post('refresh', [CustomerAuthController::class, 'refresh']);
            Route::get('me', [CustomerAuthController::class, 'me']);
            Route::put('profile', [CustomerAuthController::class, 'updateProfile']);

            Route::get('cart', [CartController::class, 'show']);
            Route::post('cart/items', [CartController::class, 'addItem']);
            Route::delete('cart/items/{bookId}', [CartController::class, 'removeItem']);
            Route::patch('cart/items/{bookId}', [CartController::class, 'updateItem']);

            Route::post('orders/checkout', [OrderController::class, 'checkout']);
            Route::post('orders/paypal/start', [PayPalController::class, 'start']);
            Route::get('orders', [OrderController::class, 'index']);
            Route::post('orders/{id}/confirm-quote', [OrderController::class, 'confirmQuote']);
            Route::get('orders/{id}', [OrderController::class, 'show']);
            Route::patch('orders/{id}/status', [OrderController::class, 'updateStatus']);
        });
    });
});
