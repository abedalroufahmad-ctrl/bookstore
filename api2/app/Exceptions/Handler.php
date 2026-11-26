<?php

namespace App\Exceptions;

use App\Http\Middleware\SetLocaleFromAcceptLanguage;
use App\Support\MessageLocalizer;
use Illuminate\Auth\AuthenticationException;
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

    /**
     * Return JSON for unauthenticated API requests instead of redirecting to route('login').
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            SetLocaleFromAcceptLanguage::apply($request);

            return response()->json([
                'success' => false,
                'message' => MessageLocalizer::localize('Unauthenticated.'),
                'data' => (object) [],
            ], 401);
        }

        return parent::unauthenticated($request, $exception);
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
        SetLocaleFromAcceptLanguage::apply($request);

        // ModelNotFound -> 404, AuthorizationException -> 403, etc. instead of a generic 500.
        $e = $this->prepareException($this->mapException($e));

        if ($e instanceof TokenExpiredException) {
            return response()->json([
                'success' => false,
                'message' => MessageLocalizer::localize('Token expired.'),
                'data' => (object) [],
            ], 401);
        }

        if ($e instanceof TokenInvalidException) {
            return response()->json([
                'success' => false,
                'message' => MessageLocalizer::localize('Token invalid.'),
                'data' => (object) [],
            ], 401);
        }

        if ($e instanceof JWTException) {
            return response()->json([
                'success' => false,
                'message' => MessageLocalizer::localize('Authorization token not found or invalid.'),
                'data' => (object) [],
            ], 401);
        }

        if ($e instanceof AuthenticationException) {
            return response()->json([
                'success' => false,
                'message' => MessageLocalizer::localize('Unauthenticated.'),
                'data' => (object) [],
            ], 401);
        }

        if ($e instanceof ValidationException) {
            $firstError = collect($e->errors())->flatten()->first();

            return response()->json([
                'success' => false,
                'message' => $firstError ? MessageLocalizer::localize($firstError) : MessageLocalizer::localize('Validation failed.'),
                'data' => [
                    'errors' => $e->errors(),
                ],
            ], 422);
        }

        if ($e instanceof HttpException) {
            $msg = $e->getMessage() ?: 'An error occurred.';
            if ($e->getStatusCode() >= 500 && ! config('app.debug')) {
                $msg = 'Server error.';
            }

            return response()->json([
                'success' => false,
                'message' => MessageLocalizer::localize($msg),
                'data' => (object) [],
            ], $e->getStatusCode());
        }

        $message = config('app.debug') ? $e->getMessage() : 'Server error.';
        $code = method_exists($e, 'getStatusCode') ? (int) $e->getStatusCode() : 500;
        if ($code < 400 || $code > 599) {
            $code = 500;
        }

        return response()->json([
            'success' => false,
            'message' => MessageLocalizer::localize($message),
            'data' => (object) [],
        ], $code);
    }
}
