<?php

namespace Database\Seeders;

use App\Models\Publisher;
use Illuminate\Database\Seeder;

class PublisherSeeder extends Seeder
{
    public function run(): void
    {
        $publishers = [
            ['name' => 'Dar Al-Shorouk', 'address' => 'Nasr City, Cairo, Egypt', 'phone' => '+20 2 24023399', 'email' => 'info@shorouk.test', 'website' => 'https://shorouk.test'],
            ['name' => 'Dar Al-Saqi', 'address' => 'Hamra, Beirut, Lebanon', 'phone' => '+961 1 866442', 'email' => 'info@alsaqi.test', 'website' => 'https://alsaqi.test'],
            ['name' => 'Hachette Antoine', 'address' => 'Sin El Fil, Beirut, Lebanon', 'phone' => '+961 1 480290', 'email' => 'contact@hachette-antoine.test', 'website' => 'https://hachette-antoine.test'],
            ['name' => 'Penguin Random House', 'address' => '1745 Broadway, New York, USA', 'phone' => '+1 212 782 9000', 'email' => 'hello@penguinrh.test', 'website' => 'https://penguinrh.test'],
            ['name' => 'HarperCollins', 'address' => '195 Broadway, New York, USA', 'phone' => '+1 212 207 7000', 'email' => 'info@harpercollins.test', 'website' => 'https://harpercollins.test'],
            ['name' => 'Oxford University Press', 'address' => 'Great Clarendon St, Oxford, UK', 'phone' => '+44 1865 556767', 'email' => 'enquiry@oup.test', 'website' => 'https://oup.test'],
            ['name' => 'Dar Al-Adab', 'address' => 'Verdun, Beirut, Lebanon', 'phone' => '+961 1 787060', 'email' => 'info@daraladab.test', 'website' => 'https://daraladab.test'],
            ['name' => 'Maktabat Jarir', 'address' => 'Olaya St, Riyadh, Saudi Arabia', 'phone' => '+966 11 462 2626', 'email' => 'support@jarir.test', 'website' => 'https://jarir.test'],
            ['name' => 'Dar Al-Maaref', 'address' => 'Corniche El Nil, Cairo, Egypt', 'phone' => '+20 2 25777077', 'email' => 'info@almaaref.test', 'website' => 'https://almaaref.test'],
            ['name' => 'Simon & Schuster', 'address' => '1230 Avenue of the Americas, New York, USA', 'phone' => '+1 212 698 7000', 'email' => 'contact@simonschuster.test', 'website' => 'https://simonschuster.test'],
        ];

        foreach ($publishers as $data) {
            Publisher::firstOrCreate(['name' => $data['name']], array_merge($data, [
                'settings' => [
                    'platform_commission_percent' => 10,
                ],
            ]));
        }
    }
}
