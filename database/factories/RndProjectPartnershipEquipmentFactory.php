<?php

namespace Database\Factories;

use App\Models\RndProject;
use App\Models\RndProjectPartnershipEquipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RndProjectPartnershipEquipment>
 */
class RndProjectPartnershipEquipmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rnd_project_id' => RndProject::factory()->partnership(),
            'product_detail_id' => fake()->unique()->numberBetween(1, 999999),
            'product_code' => fake()->bothify('EQ-###'),
            'product_name' => fake()->words(3, true),
            'uom_name' => 'PCS',
            'quantity' => fake()->randomFloat(2, 1, 20),
            'notes' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
