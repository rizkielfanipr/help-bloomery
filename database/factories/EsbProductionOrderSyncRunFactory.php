<?php

namespace Database\Factories;

use App\Enums\EsbProductionOrderSyncMode;
use App\Enums\EsbProductionOrderSyncStatus;
use App\Models\BranchEsbCode;
use App\Models\EsbProductionOrderSyncRun;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EsbProductionOrderSyncRun>
 */
class EsbProductionOrderSyncRunFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'idempotency_key' => 'test:'.Str::uuid(),
            'active_key' => null,
            'branch_esb_code_id' => null,
            'branch_id' => null,
            'company_code' => 'BLSS',
            'esb_branch_id' => 501,
            'mode' => EsbProductionOrderSyncMode::Incremental,
            'date_from' => now()->subDays(7)->toDateString(),
            'date_to' => now()->addDays(14)->toDateString(),
            'status' => EsbProductionOrderSyncStatus::Succeeded,
            'pages_fetched' => 1,
            'attempts' => 1,
            'started_at' => now(),
            'list_completed_at' => now(),
            'finished_at' => now(),
        ];
    }

    public function forMapping(BranchEsbCode $mapping): static
    {
        return $this->state(fn (array $attributes): array => [
            'branch_esb_code_id' => $mapping->id,
            'branch_id' => $mapping->branch_id,
            'company_code' => $mapping->esb_comcode,
            'esb_branch_id' => $mapping->esb_branch_id,
            'idempotency_key' => EsbProductionOrderSyncRun::windowKey($mapping, $attributes['mode'], (string) $attributes['date_from'], (string) $attributes['date_to']),
        ]);
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EsbProductionOrderSyncStatus::Running,
            'active_key' => $attributes['idempotency_key'],
            'list_completed_at' => null,
            'finished_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => EsbProductionOrderSyncStatus::Failed,
            'list_completed_at' => null,
            'error_class' => 'retryable',
            'error_summary' => 'ESB Production Order HTTP 500 [BLSS].',
        ]);
    }

    public function partial(): static
    {
        return $this->state(fn (): array => ['status' => EsbProductionOrderSyncStatus::Partial, 'details_expected' => 2, 'details_succeeded' => 1, 'details_failed' => 1]);
    }
}
