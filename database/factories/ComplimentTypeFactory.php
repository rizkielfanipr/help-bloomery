<?php

namespace Database\Factories;

use App\Models\ComplimentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComplimentType>
 */
class ComplimentTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'sort_order' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }
}
