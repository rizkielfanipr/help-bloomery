<?php

namespace Database\Factories;

use App\Enums\OrderingSource;
use App\Enums\OrderingSyncStatus;
use App\Models\Branch;
use App\Models\OrderingSyncRun;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OrderingSyncRun>
 */
class OrderingSyncRunFactory extends Factory
{
    public function definition(): array
    {
        $date = now()->subDay()->toDateString();

        return [
            'idempotency_key' => 'test:'.Str::uuid(),
            'source' => OrderingSource::DailyUsage,
            'branch_id' => Branch::factory(),
            'company_code' => 'BLSS',
            'esb_branch_code' => 'BPL',
            'period_from' => $date,
            'period_to' => $date,
            'status' => OrderingSyncStatus::Completed,
            'rows_received' => 1,
            'rows_stored' => 1,
            'pages_fetched' => 1,
            'attempts' => 1,
            'started_at' => now(),
            'finished_at' => now(),
            'correlation_id' => (string) Str::uuid(),
        ];
    }

    public function stock(): static
    {
        return $this->state(fn (): array => ['source' => OrderingSource::StockMovement]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => ['status' => OrderingSyncStatus::Failed, 'rows_stored' => 0, 'error_summary' => 'ESB HTTP 500']);
    }

    public function partial(): static
    {
        return $this->state(fn (): array => ['status' => OrderingSyncStatus::Partial, 'error_summary' => 'Pagination tidak lengkap']);
    }
}
