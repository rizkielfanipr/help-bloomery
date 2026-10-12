<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\BriefingBranchSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BriefingBranchSetting>
 */
class BriefingBranchSettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'is_enabled' => true,
            'started_at' => now()->subYear()->toDateString(),
            'ended_at' => null,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_enabled' => false,
        ]);
    }

    public function endedOn(string $date): static
    {
        return $this->state(fn (array $attributes): array => [
            'ended_at' => $date,
        ]);
    }

    public function startingOn(string $date): static
    {
        return $this->state(fn (array $attributes): array => [
            'started_at' => $date,
        ]);
    }
}
