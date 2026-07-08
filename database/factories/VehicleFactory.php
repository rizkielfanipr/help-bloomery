<?php

namespace Database\Factories;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    private static array $vehicles = [
        ['brand' => 'Toyota', 'model' => 'Avanza'],
        ['brand' => 'Toyota', 'model' => 'Kijang Innova'],
        ['brand' => 'Honda', 'model' => 'Brio'],
        ['brand' => 'Daihatsu', 'model' => 'Gran Max'],
        ['brand' => 'Mitsubishi', 'model' => 'L300'],
        ['brand' => 'Suzuki', 'model' => 'Carry'],
        ['brand' => 'Isuzu', 'model' => 'Elf'],
        ['brand' => 'Toyota', 'model' => 'Hiace'],
    ];

    public function definition(): array
    {
        $v = fake()->randomElement(self::$vehicles);
        $prefix = fake()->randomElement(['B', 'D', 'F', 'G', 'H']);
        $plate = $prefix.' '.fake()->numerify('####').' '.strtoupper(fake()->bothify('??'));

        return [
            'license_plate' => $plate,
            'brand' => $v['brand'],
            'model' => $v['model'],
            'year' => fake()->numberBetween(2015, 2024),
            'is_active' => true,
        ];
    }
}
