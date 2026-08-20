<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $segment = strtoupper($this->faker->bothify('??##'));

        return [
            'branch_id' => Branch::factory(),
            'parent_id' => null,
            'name' => 'Lokasi '.$segment,
            'type' => $this->faker->randomElement(['Gudang', 'Zona', 'Rak', 'Level', 'Bin']),
            'segment' => $segment,
            'code' => $segment,
            'is_active' => true,
        ];
    }
}
