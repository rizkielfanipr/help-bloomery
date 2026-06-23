<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Branch',
            'address' => fake()->address(),
            'lat' => null,
            'lng' => null,
            'radius_meters' => 100,
            'is_active' => true,
        ];
    }

    public function withLocation(): static
    {
        return $this->state(fn (array $attributes): array => [
            'lat' => fake()->latitude(-8.5, -6.0),
            'lng' => fake()->longitude(106.0, 112.0),
        ]);
    }
}
