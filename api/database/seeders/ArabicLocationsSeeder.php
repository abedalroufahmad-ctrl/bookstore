<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use Illuminate\Database\Seeder;
use MongoDB\BSON\ObjectId;

class ArabicLocationsSeeder extends Seeder
{
    /** ISO code fixes: repo may use different code (e.g. SR for Syria) */
    private const ISO_FIX = [
        'SR' => 'SY', // Syria
    ];

    /** English names for Arab countries when only Arabic is in JSON */
    private const COUNTRY_NAME_EN = [
        'DZ' => 'Algeria',
        'BH' => 'Bahrain',
        'KM' => 'Comoros',
        'DJ' => 'Djibouti',
        'EG' => 'Egypt',
        'IQ' => 'Iraq',
        'JO' => 'Jordan',
        'KW' => 'Kuwait',
        'LB' => 'Lebanon',
        'LY' => 'Libya',
        'MR' => 'Mauritania',
        'MA' => 'Morocco',
        'OM' => 'Oman',
        'PS' => 'Palestine',
        'QA' => 'Qatar',
        'SA' => 'Saudi Arabia',
        'SO' => 'Somalia',
        'SD' => 'Sudan',
        'SY' => 'Syria',
        'TN' => 'Tunisia',
        'AE' => 'United Arab Emirates',
        'YE' => 'Yemen',
    ];

    public function run(): void
    {
        $basePath = base_path('database/seeders/data/arabic-locations');

        $countriesPath = $basePath . '/countries.json';
        $statesPath = $basePath . '/states.json';
        $citiesPath = $basePath . '/cities.json';

        if (! file_exists($countriesPath) || ! file_exists($statesPath) || ! file_exists($citiesPath)) {
            $this->command?->warn('Arabic locations JSON not found. Run ./import-arabic-locations.sh first.');
            return;
        }

        $countriesJson = json_decode(file_get_contents($countriesPath), true);
        $statesJson = json_decode(file_get_contents($statesPath), true);
        $citiesJson = json_decode(file_get_contents($citiesPath), true);

        if (! is_array($countriesJson) || ! is_array($statesJson) || ! is_array($citiesJson)) {
            $this->command?->error('Invalid JSON in arabic-locations files.');
            return;
        }

        // Repo country id (phone_code) -> our Country MongoDB _id; also normalize ISO (e.g. SR -> SY)
        $countryIdByRepoId = [];

        foreach ($countriesJson as $row) {
            $code = isset($row['code']) ? strtoupper($row['code']) : null;
            if (! $code) {
                continue;
            }
            $code = self::ISO_FIX[$code] ?? $code;
            $nameAr = $row['name'] ?? '';
            $nameEn = self::COUNTRY_NAME_EN[$code] ?? $nameAr;

            $country = Country::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $nameEn,
                    'code' => $code,
                    'translations' => [
                        'en' => $nameEn,
                        'ar' => $nameAr,
                    ],
                ]
            );

            $repoId = (string) ($row['id'] ?? '');
            if ($repoId !== '') {
                $countryIdByRepoId[$repoId] = $country->getKey();
            }
        }

        // state_id -> repo country_id (from states JSON)
        $stateIdToCountryRepoId = [];
        foreach ($statesJson as $state) {
            $stateId = (string) ($state['id'] ?? '');
            $countryRepoId = (string) ($state['country_id'] ?? '');
            if ($stateId !== '' && $countryRepoId !== '') {
                $stateIdToCountryRepoId[$stateId] = $countryRepoId;
            }
        }

        $bar = $this->command?->getOutput()?->createProgressBar(count($citiesJson));
        $bar?->setFormat(' %current%/%max% [%bar%] %percent:3s%%');
        $bar?->start();

        $created = 0;
        foreach ($citiesJson as $city) {
            $bar?->advance();

            $stateId = (string) ($city['state_id'] ?? '');
            $countryRepoId = $stateIdToCountryRepoId[$stateId] ?? null;
            if ($countryRepoId === null || ! isset($countryIdByRepoId[$countryRepoId])) {
                continue;
            }

            $ourCountryId = $countryIdByRepoId[$countryRepoId];
            $nameAr = trim($city['name'] ?? '');
            if ($nameAr === '') {
                continue;
            }
            $nameEn = $nameAr; // dataset is Arabic-only; use same for en

            // Store countryId as ObjectId so API queries (CountryController) find cities by country
            $countryIdBson = $ourCountryId instanceof ObjectId
                ? $ourCountryId
                : new ObjectId((string) $ourCountryId);

            City::updateOrCreate(
                [
                    'countryId' => $countryIdBson,
                    'name' => $nameEn,
                ],
                [
                    'countryId' => $countryIdBson,
                    'translations' => [
                        'en' => $nameEn,
                        'ar' => $nameAr,
                    ],
                ]
            );
            $created++;
        }

        $bar?->finish();
        $this->command?->newLine();
        $this->command?->info("Arabic locations seeded: " . count($countryIdByRepoId) . " countries, {$created} cities.");
    }
}
