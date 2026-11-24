<?php

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Feature tests against a real MySQL database. The database name must end in "_test"
 * (phpunit.xml sets DB_DATABASE=book_store_test) so a dev/prod database is never wiped.
 */
abstract class MongoFeatureTestCase extends TestCase
{
    private const TABLES = [
        'publisher_commission_entries',
        'payments',
        'orders',
        'carts',
        'book_publisher',
        'author_book',
        'books',
        'employees',
        'warehouses',
        'publishers',
        'categories',
        'authors',
        'customers',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $database = (string) config('database.connections.mysql.database');
        if (! str_ends_with($database, '_test')) {
            $this->markTestSkipped("Refusing to run feature tests against non-test database [{$database}].");
        }

        try {
            DB::connection()->getPdo();
        } catch (\Throwable) {
            $this->markTestSkipped('MySQL is not reachable; skipping feature tests.');
        }

        if (! Schema::hasTable('books')) {
            $this->artisan('migrate');
        }

        $this->truncateTables();
    }

    protected function tearDown(): void
    {
        if (str_ends_with((string) config('database.connections.mysql.database'), '_test')) {
            try {
                $this->truncateTables();
            } catch (\Throwable) {
            }
        }

        parent::tearDown();
    }

    private function truncateTables(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }
        Schema::enableForeignKeyConstraints();
    }

    protected function employeeToken(Employee $employee): string
    {
        return (string) auth('employee')->login($employee);
    }

    /** @return array<string, string> */
    protected function employeeHeaders(Employee $employee): array
    {
        return [
            'Authorization' => 'Bearer '.$this->employeeToken($employee),
            'Accept' => 'application/json',
        ];
    }
}
