<?php

namespace Database\Factories;

use App\Enums\MonitoringTransactionStatus;
use App\Models\MonitoringTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Parent ESB transaction by default; use `attemptOf()` for a child attempt. Never performs an
 * external call — rows are purely local fixtures.
 *
 * @extends Factory<MonitoringTransaction>
 */
class MonitoringTransactionFactory extends Factory
{
    public function definition(): array
    {
        $startedAt = now()->subMinutes(fake()->numberBetween(1, 600));

        return [
            'uuid' => (string) Str::uuid(),
            'environment' => config('monitoring.environment'),
            'source_module' => 'item_journal',
            'source_action' => 'submit',
            'operation_type' => 'create_item_journal',
            'company_code' => 'BLSS',
            'correlation_id' => (string) Str::uuid(),
            'status' => MonitoringTransactionStatus::Succeeded,
            'attempt_count' => 1,
            'mutation_sent' => true,
            'request_summary' => ['itemJournalDate' => '2026-10-20'],
            'response_summary' => ['status' => 'ok'],
            'started_at' => $startedAt,
            'completed_at' => $startedAt->copy()->addSecond(),
            'duration_ms' => 1000,
        ];
    }

    public function status(MonitoringTransactionStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }

    public function attemptOf(MonitoringTransaction $parent): static
    {
        return $this->state(fn (): array => [
            'parent_id' => $parent->id,
            'environment' => $parent->environment,
            'source_module' => $parent->source_module,
            'source_action' => $parent->source_action,
            'correlation_id' => $parent->correlation_id,
            'method' => 'POST',
            'endpoint_template' => '/inventory/item-journal',
            'target_key' => 'journal',
        ]);
    }
}
