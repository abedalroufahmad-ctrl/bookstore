<?php

namespace App\Providers;

use App\Domain\Auth\Interfaces\CustomerAuthServiceInterface;
use App\Domain\Auth\Interfaces\EmployeeAuthServiceInterface;
use App\Domain\Author\Interfaces\AuthorRepositoryInterface;
use App\Domain\Book\Interfaces\BookRepositoryInterface;
use App\Domain\Cart\Interfaces\CartRepositoryInterface;
use App\Domain\Cart\Interfaces\CartServiceInterface;
use App\Domain\Category\Interfaces\CategoryRepositoryInterface;
use App\Domain\Customer\Interfaces\CustomerRepositoryInterface;
use App\Domain\Employee\Interfaces\EmployeeRepositoryInterface;
use App\Domain\Order\Interfaces\OrderRepositoryInterface;
use App\Domain\Order\Interfaces\OrderServiceInterface;
use App\Domain\Order\Interfaces\StockServiceInterface;
use App\Domain\Publisher\Interfaces\PublisherRepositoryInterface;
use App\Domain\Warehouse\Interfaces\WarehouseRepositoryInterface;
use App\Infrastructure\Repositories\Mongo\AuthorRepository;
use App\Infrastructure\Repositories\Mongo\BookRepository;
use App\Infrastructure\Repositories\Mongo\CartRepository;
use App\Infrastructure\Repositories\Mongo\CategoryRepository;
use App\Infrastructure\Repositories\Mongo\CustomerRepository;
use App\Infrastructure\Repositories\Mongo\EmployeeRepository;
use App\Infrastructure\Repositories\Mongo\OrderRepository;
use App\Infrastructure\Repositories\Mongo\PublisherRepository;
use App\Infrastructure\Repositories\Mongo\WarehouseRepository;
use App\Infrastructure\Services\StockService;
use App\Services\CartService;
use App\Services\CustomerAuthService;
use App\Services\EmployeeAuthService;
use App\Services\OrderService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EmployeeAuthServiceInterface::class, EmployeeAuthService::class);
        $this->app->bind(CustomerAuthServiceInterface::class, CustomerAuthService::class);

        $this->app->bind(WarehouseRepositoryInterface::class, WarehouseRepository::class);
        $this->app->bind(AuthorRepositoryInterface::class, AuthorRepository::class);
        $this->app->bind(CategoryRepositoryInterface::class, CategoryRepository::class);
        $this->app->bind(BookRepositoryInterface::class, BookRepository::class);
        $this->app->bind(PublisherRepositoryInterface::class, PublisherRepository::class);
        $this->app->bind(EmployeeRepositoryInterface::class, EmployeeRepository::class);
        $this->app->bind(CustomerRepositoryInterface::class, CustomerRepository::class);
        $this->app->bind(CartRepositoryInterface::class, CartRepository::class);
        $this->app->bind(OrderRepositoryInterface::class, OrderRepository::class);
        $this->app->bind(CartServiceInterface::class, CartService::class);
        $this->app->bind(OrderServiceInterface::class, OrderService::class);
        $this->app->bind(StockServiceInterface::class, StockService::class);
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $userKey = $this->rateLimitUserKey($request);

            return $userKey !== null
                ? Limit::perMinute(config('rate_limits.user_per_minute'))->by($userKey)
                : Limit::perMinute(config('rate_limits.guest_per_minute'))->by('ip:'.$request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            $email = mb_strtolower(trim((string) $request->input('email')));

            return [
                Limit::perMinute(config('rate_limits.login_per_minute'))->by('login:'.$email.'|'.$request->ip()),
                Limit::perMinute(config('rate_limits.login_ip_per_minute'))->by('login-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('heavy', function (Request $request) {
            return Limit::perMinute(config('rate_limits.heavy_per_minute'))
                ->by('heavy:'.($this->rateLimitUserKey($request) ?? 'ip:'.$request->ip()));
        });
    }

    /**
     * Throttling runs before route auth middleware, so resolve the JWT here. Invalid or
     * forged tokens resolve to null and fall back to the stricter per-IP guest limit.
     */
    private function rateLimitUserKey(Request $request): ?string
    {
        if (! $request->bearerToken()) {
            return null;
        }

        foreach (['employee', 'customer'] as $guard) {
            try {
                $user = auth($guard)->user();
            } catch (\Throwable) {
                $user = null;
            }
            if ($user) {
                return $guard.':'.$user->getAuthIdentifier();
            }
        }

        return null;
    }
}
