<?php

namespace Database\Factories;

use App\Enums\OrderingSyncStatus;
use App\Models\Branch;
use App\Models\OrderingForecastRun;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OrderingForecastRun>
 */
class OrderingForecastRunFactory extends Factory
{
    public function definition(): array
    {
        $cutoff = now()->subDay()->startOfDay();

        return [
            'idempotency_key' => 'test:'.Str::uuid(),
            'branch_id' => Branch::factory(),
            'horizon_days' => 7,
            'history_from' => $cutoff->copy()->subDays(55)->toDateString(),
            'history_to' => $cutoff->toDateString(),
            'forecast_from' => $cutoff->copy()->addDay()->toDateString(),
            'forecast_to' => $cutoff->copy()->addDays(7)->toDateString(),
            'algorithm_version' => 'v1',
            'configuration_hash' => str_repeat('a', 64),
            'configuration_snapshot' => ['algorithm_version' => 'v1'],
            'data_cutoff_at' => $cutoff->copy()->endOfDay(),
            'status' => OrderingSyncStatus::Completed,
            'completeness' => ['sources' => [], 'warnings' => []],
            'items_count' => 0,
            'generated_at' => now(),
            'correlation_id' => (string) Str::uuid(),
        ];
    }

    public function horizon(int $days): static
    {
        return $this->state(fn (array $attributes): array => ['horizon_days' => $days]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => ['status' => OrderingSyncStatus::Failed, 'error_summary' => 'Forecast gagal']);
    }
}
