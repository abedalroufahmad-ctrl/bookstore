<?php

use App\Exceptions\Handler;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\RestrictPublisherManagerToScopedRoutes;
use App\Http\Middleware\RestrictWarehouseManagerToScopedRoutes;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SetLocaleFromAcceptLanguage;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Tymon\JWTAuth\Http\Middleware\Authenticate;
use Tymon\JWTAuth\Http\Middleware\RefreshToken;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'restrict.warehouse_manager' => RestrictWarehouseManagerToScopedRoutes::class,
            'restrict.publisher_manager' => RestrictPublisherManagerToScopedRoutes::class,
            'force.json' => ForceJsonResponse::class,
            'jwt.auth' => Authenticate::class,
            'jwt.refresh' => RefreshToken::class,
        ]);
        $middleware->appendToGroup('api', [
            SetLocaleFromAcceptLanguage::class,
            ForceJsonResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return app(Handler::class)->render($request, $e);
            }
        });
    })->create();
