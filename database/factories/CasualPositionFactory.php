<?php

namespace Database\Factories;

use App\Models\CasualPosition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CasualPosition>
 */
class CasualPositionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Kasir', 'Pramuniaga', 'Loader', 'Picker', 'Packer']),
            'fee_per_day' => fake()->randomElement([80000, 100000, 120000, 150000]),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
