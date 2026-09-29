<?php

namespace Database\Factories;

use App\Enums\RndBomChangeLogSource;
use App\Enums\RndBomChangeLogStatus;
use App\Models\RndBomChangeLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RndBomChangeLog>
 */
class RndBomChangeLogFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'esb_bom_id' => $this->faker->numberBetween(1, 999999),
            'bom_code' => 'BOM-'.$this->faker->unique()->bothify('???###'),
            'bom_name' => $this->faker->words(3, true).' Assembly',
            'product_code' => $this->faker->bothify('PRD-###'),
            'product_name' => $this->faker->words(2, true),
            'source' => RndBomChangeLogSource::BomAdjustment,
            'event' => 'component_updated',
            'status' => RndBomChangeLogStatus::Success,
            'reason' => $this->faker->sentence(),
            'before_snapshot' => null,
            'requested_snapshot' => null,
            'after_snapshot' => null,
            'changes' => null,
            'error_code' => null,
            'error_message' => null,
            'esb_edited_at_before' => null,
            'esb_edited_at_after' => null,
            'changed_by' => User::factory(),
            'reconciled_by' => null,
            'reconciled_at' => null,
        ];
    }
}
