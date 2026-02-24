<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $e)
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return $this->apiResponse($request, $e);
        }

        return parent::render($request, $e);
    }

    private function apiResponse($request, Throwable $e)
    {
        if ($e instanceof TokenExpiredException) {
            return response()->json([
                'success' => false,
                'message' => 'Token expired.',
                'data' => (object) [],
            ], 401);
        }

        if ($e instanceof TokenInvalidException) {
            return response()->json([
                'success' => false,
                'message' => 'Token invalid.',
                'data' => (object) [],
            ], 401);
        }

        if ($e instanceof JWTException) {
            return response()->json([
                'success' => false,
                'message' => 'Authorization token not found or invalid.',
                'data' => (object) [],
            ], 401);
        }

        if ($e instanceof ValidationException) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'data' => [
                    'errors' => $e->errors(),
                ],
            ], 422);
        }

        if ($e instanceof HttpException) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'An error occurred.',
                'data' => (object) [],
            ], $e->getStatusCode());
        }

        $message = config('app.debug') ? $e->getMessage() : 'Server error.';
        $code = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => (object) [],
        ], $code);
    }
}
