<?php

namespace Database\Factories;

use App\Enums\BriefingPeriod;
use App\Models\Branch;
use App\Models\BriefingRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BriefingRecord>
 */
class BriefingRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'user_id' => fn (array $attributes): int => User::factory()->create([
                'is_active' => true,
                'branch_id' => $attributes['branch_id'],
            ])->id,
            'period' => BriefingPeriod::Daily->value,
            'record_date' => now()->toDateString(),
            'submitted_at' => now(),
        ];
    }

    public function forBranch(Branch $branch): static
    {
        return $this->state(fn (array $attributes): array => [
            'branch_id' => $branch->id,
        ]);
    }

    public function submittedBy(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user->id,
            'branch_id' => $user->branch_id,
        ]);
    }

    public function forPeriod(BriefingPeriod $period, ?string $recordDate = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'period' => $period->value,
            'record_date' => $recordDate ?? $attributes['record_date'],
        ]);
    }

    public function onDate(string $recordDate): static
    {
        return $this->state(fn (array $attributes): array => [
            'record_date' => $recordDate,
        ]);
    }

    public function unsubmitted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'submitted_at' => null,
        ]);
    }
}
