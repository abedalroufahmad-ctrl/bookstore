<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $warehouse = Warehouse::first() ?? Warehouse::create([
            'name' => 'Main Warehouse',
            'address' => '123 Storage St',
            'country' => 'USA',
            'city' => 'New York',
            'phone' => '+1234567890',
            'email' => 'warehouse@bookstore.test',
            'location' => [
                'type' => 'Point',
                'coordinates' => [-73.935242, 40.730610],
            ],
        ]);

        Employee::firstOrCreate(
            ['email' => 'admin@bookstore.test'],
            [
                'name' => 'Admin',
                'email' => 'admin@bookstore.test',
                'phone' => '+1234567890',
                'password' => 'password',
                'role' => 'manager',
                'warehouse_id' => $warehouse->getKey(),
            ]
        );

        Employee::firstOrCreate(
            ['email' => 'manager@bookstore.test'],
            [
                'name' => 'Manager User',
                'email' => 'manager@bookstore.test',
                'phone' => '+1234567891',
                'password' => 'password',
                'role' => 'manager',
                'warehouse_id' => $warehouse->getKey(),
            ]
        );

        Employee::firstOrCreate(
            ['email' => 'shipping@bookstore.test'],
            [
                'name' => 'Shipping User',
                'email' => 'shipping@bookstore.test',
                'phone' => '+1234567892',
                'password' => 'password',
                'role' => 'shipping',
                'warehouse_id' => $warehouse->getKey(),
            ]
        );

        Employee::firstOrCreate(
            ['email' => 'warehouse-manager@bookstore.test'],
            [
                'name' => 'Warehouse Manager',
                'email' => 'warehouse-manager@bookstore.test',
                'phone' => '+1234567893',
                'password' => 'password',
                'role' => 'warehouse_manager',
                'warehouse_id' => $warehouse->getKey(),
            ]
        );
    }
}
