<?php

use App\Http\Controllers\Api\Admin\AdminOrderController;
use App\Http\Controllers\Api\Admin\AuthorController;
use App\Http\Controllers\Api\Admin\BookController;
use App\Http\Controllers\Api\Admin\UploadAuthorPhotoController;
use App\Http\Controllers\Api\Admin\UploadCoverController;
use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\CustomerController;
use App\Http\Controllers\Api\Admin\EmployeeController;
use App\Http\Controllers\Api\Admin\CountryController;
use App\Http\Controllers\Api\Admin\SettingController;
use App\Http\Controllers\Api\Admin\WarehouseController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CustomerAuthController;
use App\Http\Controllers\Api\EmployeeAuthController;
use App\Http\Controllers\Api\EmployeeOrderController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PublicAuthorController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\PublicBookController;
use App\Http\Controllers\Api\PublicCategoryController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:60,1')->prefix('v1')->group(function () {
    // Payment webhooks (no auth; verify signature in controller)
    Route::post('webhooks/stripe', [WebhookController::class, 'stripe']);
    Route::post('webhooks/paypal', [WebhookController::class, 'paypal']);

    // Public Catalog (no auth) - for customers to browse
    Route::get('books', [PublicBookController::class, 'index']);
    Route::get('books/{id}', [PublicBookController::class, 'show']);
    Route::get('categories', [PublicCategoryController::class, 'index']);
    Route::get('categories/{id}', [PublicCategoryController::class, 'show']);
    Route::get('authors', [PublicAuthorController::class, 'index']);
    Route::get('authors/{id}', [PublicAuthorController::class, 'show']);
    Route::get('settings', [SettingController::class, 'index']);

    // Admin Management (manager, shipping, review, accounting, employee, warehouse_manager)
    Route::prefix('admin')->middleware(['auth:employee', 'role:manager,shipping,review,accounting,employee,warehouse_manager', 'restrict.warehouse_manager'])->group(function () {
        Route::post('upload-cover', UploadCoverController::class);
        Route::post('upload-author-photo', UploadAuthorPhotoController::class);
        Route::get('books', [BookController::class, 'index']);
        Route::post('books', [BookController::class, 'store']);
        Route::get('books/{id}', [BookController::class, 'show']);
        Route::put('books/{id}', [BookController::class, 'update']);
        Route::delete('books/{id}', [BookController::class, 'destroy']);

        Route::get('warehouses', [WarehouseController::class, 'index']);
        Route::post('warehouses', [WarehouseController::class, 'store']);
        Route::get('warehouses/{id}', [WarehouseController::class, 'show']);
        Route::put('warehouses/{id}', [WarehouseController::class, 'update']);
        Route::delete('warehouses/{id}', [WarehouseController::class, 'destroy']);

        Route::get('authors', [AuthorController::class, 'index']);
        Route::post('authors', [AuthorController::class, 'store']);
        Route::get('authors/{id}', [AuthorController::class, 'show']);
        Route::put('authors/{id}', [AuthorController::class, 'update']);
        Route::delete('authors/{id}', [AuthorController::class, 'destroy']);

        Route::get('categories', [CategoryController::class, 'index']);
        Route::post('categories', [CategoryController::class, 'store']);
        Route::get('categories/{id}', [CategoryController::class, 'show']);
        Route::put('categories/{id}', [CategoryController::class, 'update']);
        Route::delete('categories/{id}', [CategoryController::class, 'destroy']);

        Route::get('employees', [EmployeeController::class, 'index']);
        Route::post('employees', [EmployeeController::class, 'store']);
        Route::get('employees/{id}', [EmployeeController::class, 'show']);
        Route::put('employees/{id}', [EmployeeController::class, 'update']);

        Route::get('customers', [CustomerController::class, 'index']);
        Route::get('customers/{id}', [CustomerController::class, 'show']);

        Route::get('orders', [AdminOrderController::class, 'index']);
        Route::get('orders/{id}', [AdminOrderController::class, 'show']);
        Route::patch('orders/{id}/status', [AdminOrderController::class, 'updateStatus']);
        Route::post('orders/{id}/assign', [AdminOrderController::class, 'assign']);

        Route::get('settings', [SettingController::class, 'index']);
        Route::put('settings', [SettingController::class, 'update']);

        Route::get('countries', [CountryController::class, 'index']);
        Route::post('countries/sync-from-network', [CountryController::class, 'syncFromNetwork']);
        Route::post('countries/sync-cities-from-dataset', [CountryController::class, 'syncCitiesFromDataset']);
        Route::get('countries/{id}', [CountryController::class, 'show']);
    });

    // Employee Auth (login only, no register - employees created by admin)
    Route::prefix('employees')->group(function () {
        Route::post('login', [EmployeeAuthController::class, 'login']);

        Route::middleware(['auth:employee'])->group(function () {
            Route::post('logout', [EmployeeAuthController::class, 'logout']);
            Route::post('refresh', [EmployeeAuthController::class, 'refresh']);
            Route::get('me', [EmployeeAuthController::class, 'me']);

            // Order management (role-protected)
            Route::middleware('role:manager,shipping,review,accounting,employee,warehouse_manager')->group(function () {
                Route::get('orders', [EmployeeOrderController::class, 'index']);
                Route::get('orders/{id}', [EmployeeOrderController::class, 'show']);
                Route::patch('orders/{id}/status', [EmployeeOrderController::class, 'updateStatus']);
            });
        });
    });

    // Customer Auth
    Route::prefix('customers')->group(function () {
        Route::post('register', [CustomerAuthController::class, 'register']);
        Route::post('login', [CustomerAuthController::class, 'login']);

        Route::middleware(['auth:customer'])->group(function () {
            Route::post('logout', [CustomerAuthController::class, 'logout']);
            Route::post('refresh', [CustomerAuthController::class, 'refresh']);
            Route::get('me', [CustomerAuthController::class, 'me']);
            Route::put('profile', [CustomerAuthController::class, 'updateProfile']);

            // Cart
            Route::get('cart', [CartController::class, 'show']);
            Route::post('cart/items', [CartController::class, 'addItem']);
            Route::delete('cart/items/{bookId}', [CartController::class, 'removeItem']);
            Route::patch('cart/items/{bookId}', [CartController::class, 'updateItem']);

            // Orders
            Route::post('orders/checkout', [OrderController::class, 'checkout']);
            Route::get('orders', [OrderController::class, 'index']);
            Route::get('orders/{id}', [OrderController::class, 'show']);
            Route::patch('orders/{id}/status', [OrderController::class, 'updateStatus']);
        });
    });
});
