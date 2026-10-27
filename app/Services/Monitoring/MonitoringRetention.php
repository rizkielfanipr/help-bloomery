<?php

namespace App\Services\Monitoring;

use App\Enums\MonitoringErrorStatus;
use App\Models\MonitoringAlert;
use App\Models\MonitoringCheckResult;
use App\Models\MonitoringDailyAggregate;
use App\Models\MonitoringDeployment;
use App\Models\MonitoringError;
use App\Models\MonitoringIncidentLink;
use App\Models\MonitoringScheduledTaskRun;
use App\Models\MonitoringTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Retention (docs/operations/monitoring/prd.md §19). Processes rows in id chunks, is idempotent, and
 * never deletes incidents, incident timelines, or records linked to an incident. Payload expiry
 * clears sanitized summaries but keeps the transaction, its status, and its attempts.
 */
class MonitoringRetention
{
    public function __construct(private readonly MonitoringSettings $settings) {}

    /** @return array<string, int> */
    public function run(bool $dryRun = false): array
    {
        $days = fn (string $key, int $fallback): int => max(1, (int) $this->settings->get('retention.'.$key, $fallback));

        $summary = [];
        $summary['payloads_expired'] = $this->expirePayloads(now()->subDays($days('payload_days', 60)), $dryRun);
        $summary['check_results_deleted'] = $this->delete(MonitoringCheckResult::query()->where('checked_at', '<', now()->subDays($days('check_results_days', 30))), $dryRun);
        $summary['transactions_deleted'] = $this->delete(
            $this->unlinked(MonitoringTransaction::query()->parents()->where('started_at', '<', now()->subDays($days('transactions_days', 180))), 'transaction'),
            $dryRun,
        );
        $summary['errors_deleted'] = $this->delete(
            $this->unlinked(MonitoringError::query()
                ->whereIn('status', [MonitoringErrorStatus::Resolved->value, MonitoringErrorStatus::Ignored->value])
                ->where('last_seen_at', '<', now()->subDays($days('errors_days', 90))), 'error'),
            $dryRun,
        );
        $summary['alerts_deleted'] = $this->delete(
            $this->unlinked(MonitoringAlert::query()->whereNull('active_dedup_key')->whereNull('monitoring_incident_id')->where('resolved_at', '<', now()->subDays($days('transactions_days', 180))), 'alert'),
            $dryRun,
        );
        $summary['deployments_deleted'] = $this->delete(
            $this->unlinked(MonitoringDeployment::query()->where('created_at', '<', now()->subDays($days('deployments_days', 730))), 'deployment'),
            $dryRun,
        );
        $summary['task_runs_deleted'] = $this->delete(MonitoringScheduledTaskRun::query()->where('started_at', '<', now()->subDays((int) config('monitoring.retention.scheduled_task_runs_days', 30))), $dryRun);
        $summary['aggregates_deleted'] = $this->delete(MonitoringDailyAggregate::query()->where('date', '<', now()->subDays((int) config('monitoring.retention.aggregates_days', 730))->toDateString()), $dryRun);

        return $summary;
    }

    private function expirePayloads(\DateTimeInterface $before, bool $dryRun): int
    {
        $query = MonitoringTransaction::query()->whereNull('payload_expired_at')->where('started_at', '<', $before);
        if ($dryRun) {
            return $query->count();
        }

        $count = 0;
        $query->select('id')->chunkById(500, function ($rows) use (&$count): void {
            $count += MonitoringTransaction::query()->whereKey($rows->pluck('id'))->update([
                'request_summary' => null,
                'response_summary' => null,
                'payload_expired_at' => now(),
            ]);
        });

        return $count;
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function unlinked(Builder $query, string $type): Builder
    {
        $table = $query->getModel()->getTable();

        return $query->whereNotExists(fn ($links) => $links->selectRaw('1')
            ->from((new MonitoringIncidentLink)->getTable())
            ->where('linkable_type', $type)
            ->whereColumn('linkable_id', $table.'.id'));
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function delete(Builder $query, bool $dryRun): int
    {
        if ($dryRun) {
            return $query->count();
        }

        $model = $query->getModel();
        $count = 0;
        $query->select($model->getTable().'.id')->chunkById(500, function ($rows) use ($model, &$count): void {
            $count += $model->newQuery()->whereKey($rows->pluck('id'))->delete();
        }, $model->getTable().'.id', 'id');

        return $count;
    }
}
