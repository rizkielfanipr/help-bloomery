<?php

namespace Database\Seeders;

use App\Models\FuelType;
use Illuminate\Database\Seeder;

class FuelTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Pertalite',         'price_per_liter' => 10000],
            ['name' => 'Pertamax',          'price_per_liter' => 13900],
            ['name' => 'Pertamax Turbo',    'price_per_liter' => 14000],
            ['name' => 'Pertamax Green 95', 'price_per_liter' => 13500],
            ['name' => 'Dexlite',           'price_per_liter' => 13950],
            ['name' => 'Pertamina Dex',     'price_per_liter' => 14950],
            ['name' => 'Solar',             'price_per_liter' => 6800],
            ['name' => 'Biosolar',          'price_per_liter' => 6800],
        ];

        foreach ($types as $i => $data) {
            FuelType::updateOrCreate(
                ['name' => $data['name']],
                ['is_active' => true, 'sort_order' => $i + 1, 'price_per_liter' => $data['price_per_liter']]
            );
        }
    }
}
