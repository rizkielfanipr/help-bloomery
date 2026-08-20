<?php

namespace Database\Factories;

use App\Models\ProductSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductSetting>
 */
class ProductSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_code' => strtoupper($this->faker->unique()->bothify('PRD-####')),
        ];
    }
}
