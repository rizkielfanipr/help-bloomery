<?php

namespace Database\Factories;

use App\Models\MaterialSourcing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaterialSourcing>
 */
class MaterialSourcingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_name' => $this->faker->company(),
            'brand' => $this->faker->company(),
            'price' => $this->faker->randomFloat(2, 1000, 500000),
            'moq' => $this->faker->numberBetween(10, 500).' kg',
            'lead_time_days' => $this->faker->numberBetween(1, 30),
            'contact_name' => $this->faker->name(),
            'contact_phone' => $this->faker->phoneNumber(),
        ];
    }
}
