<?php

namespace Database\Factories;

use App\Models\StockCardSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockCardSetting>
 */
class StockCardSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_code' => strtoupper(fake()->unique()->bothify('COM###')),
            'all_categories' => true,
            'categories' => [],
            'show_uncategorized' => true,
        ];
    }
}
