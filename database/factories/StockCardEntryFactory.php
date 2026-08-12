<?php

namespace Database\Factories;

use App\Models\StockCard;
use App\Models\StockCardEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockCardEntry>
 */
class StockCardEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stock_card_id' => StockCard::factory(),
            'product_code' => strtoupper(fake()->lexify('MAT-????')),
            'product_name' => fake()->words(3, true),
            'system_qty' => null,
            'system_unit' => fake()->randomElement(['PCS', 'KG', 'Ekor', 'Liter']),
            'is_manual' => true,
            'actual_qty' => null,
            'reported_qty' => null,
            'notes' => null,
            'supervisor_notes' => null,
        ];
    }
}
