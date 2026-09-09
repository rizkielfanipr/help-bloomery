<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name' => fake()->randomElement(['Mesin Espresso', 'Chiller', 'Blender', 'Freezer']),
            'category' => 'Equipment',
            'brand' => fake()->company(),
            'model' => strtoupper(fake()->bothify('MDL-###')),
            'serial_number' => strtoupper(fake()->bothify('SN-########')),
            'qr_token' => (string) Str::uuid(),
            'is_active' => true,
        ];
    }
}
