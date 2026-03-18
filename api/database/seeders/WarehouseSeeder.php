<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $warehouses = [
            [
                'name' => 'Test Warehouse',
                'address' => '456 Test Ave',
                'country' => 'USA',
                'city' => 'Los Angeles',
                'phone' => '+1987654321',
                'email' => 'test-warehouse@bookstore.test',
                'location' => [
                    'type' => 'Point',
                    'coordinates' => [-118.243683, 34.052235],
                ],
            ],
            // Syria
            [
                'name' => 'Damascus Warehouse',
                'address' => 'Bab Touma',
                'country' => 'Syria',
                'city' => 'Damascus',
                'phone' => '+963112345678',
                'email' => 'damascus-warehouse@bookstore.test',
            ],
            [
                'name' => 'Aleppo Warehouse',
                'address' => 'Al Jamiliya',
                'country' => 'Syria',
                'city' => 'Aleppo',
                'phone' => '+963212345678',
                'email' => 'aleppo-warehouse@bookstore.test',
            ],
            [
                'name' => 'Homs Warehouse',
                'address' => 'City Center',
                'country' => 'Syria',
                'city' => 'Homs',
                'phone' => '+963312345678',
                'email' => 'homs-warehouse@bookstore.test',
            ],
            // Other Arabic countries
            [
                'name' => 'Cairo Warehouse',
                'address' => 'Downtown',
                'country' => 'Egypt',
                'city' => 'Cairo',
                'phone' => '+20223456789',
                'email' => 'cairo-warehouse@bookstore.test',
            ],
            [
                'name' => 'Riyadh Warehouse',
                'address' => 'Olaya',
                'country' => 'Saudi Arabia',
                'city' => 'Riyadh',
                'phone' => '+966112345678',
                'email' => 'riyadh-warehouse@bookstore.test',
            ],
            [
                'name' => 'Dubai Warehouse',
                'address' => 'Business Bay',
                'country' => 'United Arab Emirates',
                'city' => 'Dubai',
                'phone' => '+97141234567',
                'email' => 'dubai-warehouse@bookstore.test',
            ],
            [
                'name' => 'Amman Warehouse',
                'address' => 'Abdali',
                'country' => 'Jordan',
                'city' => 'Amman',
                'phone' => '+96264234567',
                'email' => 'amman-warehouse@bookstore.test',
            ],
            [
                'name' => 'Beirut Warehouse',
                'address' => 'Hamra',
                'country' => 'Lebanon',
                'city' => 'Beirut',
                'phone' => '+9611123456',
                'email' => 'beirut-warehouse@bookstore.test',
            ],
        ];

        foreach ($warehouses as $data) {
            Warehouse::firstOrCreate(
                ['name' => $data['name'], 'city' => $data['city']],
                $data
            );
        }
    }
}
