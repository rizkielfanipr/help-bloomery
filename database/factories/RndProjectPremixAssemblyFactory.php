<?php

namespace Database\Factories;

use App\Models\RndProject;
use App\Models\RndProjectPremixAssembly;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RndProjectPremixAssembly>
 */
class RndProjectPremixAssemblyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rnd_project_id' => RndProject::factory()->premix(),
            'esb_bom_id' => fake()->unique()->numberBetween(1, 999999),
            'bom_code' => mb_strtoupper(fake()->bothify('BOM-####')),
            'bom_name' => fake()->words(3, true).' Assembly',
            'product_name' => fake()->words(2, true),
            'uom_name' => 'PCS',
            'bom_type_id' => 1,
            'bom_type_name' => 'Assembly',
            'detail_snapshot' => [],
            'catalog_is_active' => true,
            'catalog_synced_at' => now(),
            'created_by' => User::factory(),
        ];
    }
}
