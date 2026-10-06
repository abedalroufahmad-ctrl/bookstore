<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ensures indexes for admin order lists, POS reports, scoped employee lookups and carts.
 * Indexes whose key pattern already exists (under any name) are skipped, so this is safe
 * on databases where earlier index migrations ran and on ones where collections were
 * dropped and recreated without them.
 */
return new class extends Migration
{
    protected $connection = 'mongodb';

    /** @var array<string, array<string, array<string, int>>> collection => [name => keys] */
    private const INDEXES = [
        'orders' => [
            'orders_customer_created_idx' => ['customer_id' => 1, 'created_at' => -1],
            'orders_status_created_idx' => ['status' => 1, 'created_at' => -1],
            'orders_warehouse_created_idx' => ['warehouse_id' => 1, 'created_at' => -1],
            'orders_employee_created_idx' => ['employee_id' => 1, 'created_at' => -1],
            'orders_direct_sale_created_idx' => ['is_direct_sale' => 1, 'created_at' => -1],
            'orders_payment_status_idx' => ['payment_status' => 1],
            'orders_created_idx' => ['created_at' => -1],
        ],
        'carts' => [
            'carts_customer_status_idx' => ['customer_id' => 1, 'status' => 1],
        ],
        'employees' => [
            'employees_warehouse_ids_idx' => ['warehouse_ids' => 1],
            'employees_publisher_id_idx' => ['publisher_id' => 1],
        ],
        'books' => [
            'books_publisher_ids_idx' => ['publisher_ids' => 1],
        ],
        'warehouses' => [
            'warehouses_publisher_id_idx' => ['publisher_id' => 1],
        ],
    ];

    public function up(): void
    {
        $db = DB::connection('mongodb')->getMongoDB();

        foreach (self::INDEXES as $collectionName => $indexes) {
            $collection = $db->selectCollection($collectionName);
            $existing = [];
            foreach ($collection->listIndexes() as $info) {
                $existing[] = json_encode((array) $info->getKey());
            }

            foreach ($indexes as $name => $keys) {
                if (in_array(json_encode($keys), $existing, true)) {
                    continue;
                }
                $collection->createIndex($keys, ['name' => $name]);
            }
        }
    }

    public function down(): void
    {
        $db = DB::connection('mongodb')->getMongoDB();

        foreach (self::INDEXES as $collectionName => $indexes) {
            $collection = $db->selectCollection($collectionName);
            $names = [];
            foreach ($collection->listIndexes() as $info) {
                $names[] = $info->getName();
            }
            foreach (array_keys($indexes) as $name) {
                if (in_array($name, $names, true)) {
                    $collection->dropIndex($name);
                }
            }
        }
    }
};
