<?php

namespace Database\Factories;

use App\Models\QualityControlChecklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QualityControlChecklistItem>
 */
class QualityControlChecklistItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'section_code' => fake()->randomElement(['A', 'B', 'C', 'D', 'E', 'F']),
            'section_name' => fake()->words(3, true),
            'question' => fake()->unique()->sentence(),
            'check_procedure' => fake()->sentence(),
            'points' => fake()->numberBetween(1, 15),
            'is_critical' => false,
            'requires_photo' => false,
            'is_active' => true,
            'sort_order' => fake()->unique()->numberBetween(1, 5000),
        ];
    }
}
