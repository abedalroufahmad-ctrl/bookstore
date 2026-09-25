<?php

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Feature tests against a real MongoDB database. The database name must end in "_test"
 * (phpunit.xml sets MONGODB_DATABASE=book_store_test) so a dev/prod database is never wiped.
 */
abstract class MongoFeatureTestCase extends TestCase
{
    private const COLLECTIONS = [
        'books', 'orders', 'payments', 'carts', 'customers', 'employees',
        'warehouses', 'publishers', 'categories', 'authors',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $database = (string) config('database.connections.mongodb.database');
        if (! str_ends_with($database, '_test')) {
            $this->markTestSkipped("Refusing to run feature tests against non-test database [{$database}].");
        }

        try {
            DB::connection('mongodb')->getMongoDB()->command(['ping' => 1]);
        } catch (\Throwable) {
            $this->markTestSkipped('MongoDB is not reachable; skipping feature tests.');
        }

        $this->truncateCollections();
    }

    protected function tearDown(): void
    {
        if (str_ends_with((string) config('database.connections.mongodb.database'), '_test')) {
            try {
                $this->truncateCollections();
            } catch (\Throwable) {
            }
        }

        parent::tearDown();
    }

    private function truncateCollections(): void
    {
        $db = DB::connection('mongodb')->getMongoDB();
        foreach (self::COLLECTIONS as $collection) {
            $db->selectCollection($collection)->deleteMany([]);
        }
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
