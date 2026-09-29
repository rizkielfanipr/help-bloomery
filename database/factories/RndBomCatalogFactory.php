<?php

namespace Database\Factories;

use App\Enums\RndBomCatalogSyncStatus;
use App\Models\RndBomCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RndBomCatalog>
 */
class RndBomCatalogFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'esb_bom_id' => $this->faker->unique()->numberBetween(1, 999999),
            'bom_code' => 'BOM-'.$this->faker->unique()->bothify('???###'),
            'bom_name' => $this->faker->words(3, true).' Assembly',
            'bom_type_id' => 1,
            'bom_type_name' => 'Assembly',
            'product_detail_id' => $this->faker->numberBetween(1, 999999),
            'product_code' => $this->faker->bothify('PRD-###'),
            'product_name' => $this->faker->words(2, true),
            'uom_name' => 'PCS',
            'component_count' => $this->faker->numberBetween(1, 10),
            'is_active' => true,
            'detail_snapshot' => null,
            'esb_edited_at' => now()->toIso8601String(),
            'sync_status' => RndBomCatalogSyncStatus::Synced,
            'last_synced_at' => now(),
        ];
    }
}
