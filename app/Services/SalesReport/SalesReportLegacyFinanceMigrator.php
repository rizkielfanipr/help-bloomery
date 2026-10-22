<?php

namespace App\Services\SalesReport;

use App\Enums\SalesReportStatus;
use App\Models\SalesReport;
use App\Models\SalesReportApproval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Converts legacy `PendingFinance` Sales Reports now that Finance approval no longer exists
 * (docs/sales-report-compliment-void-remediation-prd.md §18.4, §19.3).
 *
 * Only reports with proof of a valid Supervisor approval — a `supervisor/approved` audit row and
 * a Supervisor review time — become `Completed`; everything else is reported as an exception and
 * left alone. No Finance approval is invented and no reviewer, note, settlement, or MDR is
 * changed. Every execution writes a snapshot first so it can be restored, and a restore never
 * overwrites a report that changed after the migration.
 */
class SalesReportLegacyFinanceMigrator
{
    public const SNAPSHOT_DIRECTORY = 'sales-report-workflow-migrations';

    /**
     * @return array{total: int, candidates: list<int>, exceptions: list<array{id: int, reason: string}>, by_branch_month: list<array{branch: string, month: string, total: int}>, with_reviewer: int, with_audit: int, with_finance_or_settlement: int}
     */
    public function preview(): array
    {
        $reports = $this->legacyQuery()->with(['branch:id,name'])->withCount([
            'approvals as supervisor_approvals_count' => fn (Builder $query): Builder => $query->where('stage', 'supervisor')->where('action', 'approved'),
            'reconciliations as settled_reconciliations_count' => fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query
                ->whereNotNull('settlement_amount')->orWhereNotNull('mdr_amount')->orWhereNotNull('finance_note')),
        ])->orderBy('id')->get();

        $candidates = [];
        $exceptions = [];
        foreach ($reports as $report) {
            $reason = $this->exceptionReason($report);
            $reason === null ? $candidates[] = $report->id : $exceptions[] = ['id' => $report->id, 'reason' => $reason];
        }

        return [
            'total' => $reports->count(),
            'candidates' => $candidates,
            'exceptions' => $exceptions,
            'by_branch_month' => $reports->groupBy(fn (SalesReport $report): string => ($report->branch?->name ?? '-').'|'.$report->report_date->format('Y-m'))
                ->map(fn ($group, string $key): array => ['branch' => explode('|', $key)[0], 'month' => explode('|', $key)[1], 'total' => $group->count()])
                ->values()->all(),
            'with_reviewer' => $reports->filter(fn (SalesReport $report): bool => $report->supervisor_reviewed_by !== null && $report->supervisor_reviewed_at !== null)->count(),
            'with_audit' => $reports->where('supervisor_approvals_count', '>', 0)->count(),
            'with_finance_or_settlement' => $reports->filter(fn (SalesReport $report): bool => $report->finance_reviewed_by !== null || $report->finance_note !== null || $report->settled_reconciliations_count > 0)->count(),
        ];
    }

    /**
     * @return array{snapshot: string, converted: list<int>, skipped: list<int>}
     */
    public function execute(): array
    {
        $candidates = $this->preview()['candidates'];
        $snapshotId = now()->format('Ymd_His').'_'.Str::lower(Str::random(6));
        $entries = SalesReport::query()->whereKey($candidates)->get(['id', 'status', 'updated_at'])
            ->map(fn (SalesReport $report): array => ['id' => $report->id, 'status_before' => $report->status->value, 'updated_at_before' => $report->updated_at?->toIso8601String()])
            ->all();
        $this->writeSnapshot($snapshotId, ['id' => $snapshotId, 'created_at' => now()->toIso8601String(), 'entries' => $entries]);

        $converted = [];
        $skipped = [];
        foreach ($candidates as $id) {
            $done = DB::transaction(function () use ($id, $snapshotId): ?array {
                $report = SalesReport::query()->lockForUpdate()->find($id);
                if (! $report || $report->status !== SalesReportStatus::PendingFinance || $this->exceptionReason($report->loadCount([
                    'approvals as supervisor_approvals_count' => fn (Builder $query): Builder => $query->where('stage', 'supervisor')->where('action', 'approved'),
                ])) !== null) {
                    return null;
                }

                $report->update(['status' => SalesReportStatus::Completed->value]);
                $approval = SalesReportApproval::query()->create([
                    'sales_report_id' => $report->id,
                    'stage' => 'system',
                    'action' => 'migrated',
                    'actor_id' => null,
                    'notes' => 'Finance approval removed from the workflow; converted on the recorded Supervisor approval.',
                    'revision_number' => $report->revision_number,
                    'metadata' => ['source' => 'workflow_migration', 'previous_status' => SalesReportStatus::PendingFinance->value, 'snapshot' => $snapshotId],
                ]);

                return ['id' => $report->id, 'updated_at_after' => $report->refresh()->updated_at?->toIso8601String(), 'approval_id' => $approval->id];
            });

            $done === null ? $skipped[] = $id : $converted[] = $done;
        }

        $snapshot = $this->readSnapshot($snapshotId);
        $after = collect($converted)->keyBy('id');
        $snapshot['entries'] = collect($snapshot['entries'])->map(fn (array $entry): array => [...$entry, ...($after[$entry['id']] ?? ['skipped' => true])])->all();
        $this->writeSnapshot($snapshotId, $snapshot);

        return ['snapshot' => $snapshotId, 'converted' => collect($converted)->pluck('id')->all(), 'skipped' => $skipped];
    }

    /**
     * @return array{restored: list<int>, skipped: list<int>, conflicts: list<array{id: int, reason: string}>}
     */
    public function restore(string $snapshotId, bool $apply): array
    {
        $snapshot = $this->readSnapshot($snapshotId);
        $result = ['restored' => [], 'skipped' => [], 'conflicts' => []];

        foreach ($snapshot['entries'] as $entry) {
            if (! isset($entry['approval_id'])) {
                $result['skipped'][] = $entry['id'];

                continue;
            }

            $outcome = DB::transaction(function () use ($entry, $snapshotId, $apply): ?string {
                $report = SalesReport::query()->lockForUpdate()->find($entry['id']);
                $conflict = match (true) {
                    ! $report => 'Sales Report no longer exists.',
                    $report->status !== SalesReportStatus::Completed => 'Status changed to '.$report->status->value.' after the migration.',
                    $report->updated_at?->toIso8601String() !== $entry['updated_at_after'] => 'Sales Report was modified after the migration.',
                    $report->approvals()->where('id', '>', $entry['approval_id'])->exists() => 'Review activity was recorded after the migration.',
                    default => null,
                };
                if ($conflict !== null || ! $apply) {
                    return $conflict;
                }

                $report->update(['status' => SalesReportStatus::PendingFinance->value]);
                SalesReportApproval::query()->create([
                    'sales_report_id' => $report->id,
                    'stage' => 'system',
                    'action' => 'restored',
                    'actor_id' => null,
                    'notes' => 'Workflow migration restored.',
                    'revision_number' => $report->revision_number,
                    'metadata' => ['source' => 'workflow_migration_restore', 'previous_status' => SalesReportStatus::Completed->value, 'snapshot' => $snapshotId],
                ]);

                return null;
            });

            $outcome === null ? $result['restored'][] = $entry['id'] : $result['conflicts'][] = ['id' => $entry['id'], 'reason' => $outcome];
        }

        return $result;
    }

    private function legacyQuery(): Builder
    {
        return SalesReport::query()->where('status', SalesReportStatus::PendingFinance->value);
    }

    private function exceptionReason(SalesReport $report): ?string
    {
        return match (true) {
            $report->supervisor_approvals_count < 1 => 'No supervisor/approved audit record.',
            $report->supervisor_reviewed_at === null => 'Supervisor review time is missing.',
            default => null,
        };
    }

    /** @param array<string, mixed> $snapshot */
    private function writeSnapshot(string $snapshotId, array $snapshot): void
    {
        Storage::disk('local')->put(self::SNAPSHOT_DIRECTORY.'/'.$snapshotId.'.json', json_encode($snapshot, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    private function readSnapshot(string $snapshotId): array
    {
        $path = self::SNAPSHOT_DIRECTORY.'/'.basename($snapshotId, '.json').'.json';
        if (! Storage::disk('local')->exists($path)) {
            throw new RuntimeException("Snapshot {$snapshotId} not found.");
        }

        return json_decode((string) Storage::disk('local')->get($path), true, flags: JSON_THROW_ON_ERROR);
    }
}
