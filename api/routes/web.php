<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'success' => true,
        'message' => 'Book Store API',
        'data' => [
            'version' => '1.0',
            'docs' => '/api/v1',
        ],
    ]);
});
