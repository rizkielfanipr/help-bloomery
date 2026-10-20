<?php

namespace Database\Factories;

use App\Enums\KitchenProductionScheduleStatus;
use App\Models\Branch;
use App\Models\KitchenProductionSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KitchenProductionSchedule>
 */
class KitchenProductionScheduleFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory()->state(['is_active' => true]),
            'production_date' => today()->addDays(fake()->unique()->numberBetween(1, 3650))->toDateString(),
            'status' => KitchenProductionScheduleStatus::Scheduled,
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }

    public function forBranch(Branch $branch): static
    {
        return $this->state(fn (array $attributes): array => ['branch_id' => $branch->id]);
    }

    public function onDate(string $date): static
    {
        return $this->state(fn (array $attributes): array => ['production_date' => $date]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => KitchenProductionScheduleStatus::Scheduled]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => KitchenProductionScheduleStatus::Cancelled,
            'cancelled_by' => User::factory(),
            'cancelled_at' => now(),
            'cancellation_reason' => 'Produksi dibatalkan.',
        ]);
    }
}
