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
use App\Domain\Warehouse\Interfaces\WarehouseRepositoryInterface;
use App\Infrastructure\Repositories\Mongo\AuthorRepository;
use App\Infrastructure\Repositories\Mongo\BookRepository;
use App\Infrastructure\Repositories\Mongo\CategoryRepository;
use App\Infrastructure\Repositories\Mongo\CartRepository;
use App\Infrastructure\Repositories\Mongo\CustomerRepository;
use App\Infrastructure\Repositories\Mongo\EmployeeRepository;
use App\Infrastructure\Repositories\Mongo\OrderRepository;
use App\Infrastructure\Repositories\Mongo\WarehouseRepository;
use App\Infrastructure\Services\StockService;
use App\Services\CartService;
use App\Services\CustomerAuthService;
use App\Services\EmployeeAuthService;
use App\Services\OrderService;
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
        //
    }
}
