<?php

namespace Database\Factories;

use App\Models\KitchenProductionSchedule;
use App\Models\KitchenProductionScheduleItem;
use App\Models\RndBomCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KitchenProductionScheduleItem>
 */
class KitchenProductionScheduleItemFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'kitchen_production_schedule_id' => KitchenProductionSchedule::factory(),
            'esb_bom_id' => fake()->unique()->numberBetween(1, 999999),
            'bom_code' => 'BOM-'.fake()->bothify('???###'),
            'bom_name' => fake()->words(3, true),
            'bom_type_id' => 1,
            'product_detail_id' => fake()->numberBetween(1, 999999),
            'product_code' => fake()->bothify('PRD-###'),
            'product_name' => fake()->words(2, true),
            'uom_name' => 'PCS',
            'planned_quantity' => '10.0000',
            'sequence' => fake()->unique()->numberBetween(1, 1000000),
            'notes' => null,
        ];
    }

    /**
     * Item copied from a real catalog row, as the save Action stores it.
     */
    public function assemblyItem(RndBomCatalog $catalog): static
    {
        return $this->state(fn (array $attributes): array => [
            'rnd_bom_catalog_id' => $catalog->id,
            'esb_bom_id' => $catalog->esb_bom_id,
            'bom_code' => $catalog->bom_code,
            'bom_name' => $catalog->bom_name,
            'bom_type_id' => $catalog->bom_type_id,
            'product_detail_id' => $catalog->product_detail_id,
            'product_code' => $catalog->product_code,
            'product_name' => $catalog->product_name,
            'uom_name' => $catalog->uom_name,
        ]);
    }

    public function atSequence(int $sequence): static
    {
        return $this->state(fn (array $attributes): array => ['sequence' => $sequence]);
    }
}
