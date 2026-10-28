<?php

namespace App\Services\Kitchen;

use App\Enums\EsbProductionOrderSyncMode;
use App\Enums\EsbProductionOrderSyncStatus;
use App\Jobs\Kitchen\SyncEsbProductionOrdersJob;
use App\Models\BranchEsbCode;
use App\Models\EsbProductionOrderSyncRun;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Plans the Production Order sync in small units — one mapping and one window of at most
 * `window_max_days` per job (docs/products/kitchen/production-schedule/prd.md §28, §30.1). Master Branch is the
 * source of truth (K20): every active and complete mapping of an active Kitchen-Brand Branch is
 * synced separately with its own company context (K8, K9), so a new Kitchen Branch needs no code or
 * configuration change. Other mappings are reported with a reason and never dispatched.
 *
 * A new run is created per dispatch. Its `active_key` is unique while queued or running, so the
 * database refuses a second active run for the same window; a run that stopped moving is closed as
 * failed first, so a crashed worker cannot block the window forever.
 */
class ProductionOrderSyncPlanner
{
    /**
     * @param  list<int>  $branchIds
     * @param  list<int>  $mappingIds
     * @return array{eligible: EloquentCollection<int, BranchEsbCode>, skipped: list<array{mapping: BranchEsbCode, reason: string}>}
     */
    public function mappings(array $branchIds = [], array $mappingIds = []): array
    {
        $mappings = BranchEsbCode::query()
            ->with('branch:id,name,is_active,brand_id')
            // Kitchen-Brand Branches only, even for an explicit --branch/--mapping (prd.md §28.6, K9).
            ->whereHas('branch', fn (Builder $query): Builder => $query->kitchenBrand())
            ->when($branchIds !== [], fn (Builder $query) => $query->whereIn('branch_id', $branchIds))
            ->when($mappingIds !== [], fn (Builder $query) => $query->whereKey($mappingIds))
            ->orderBy('branch_id')
            ->orderBy('id')
            ->get();

        $eligible = new EloquentCollection;
        $skipped = [];
        foreach ($mappings as $mapping) {
            $reason = $this->skipReason($mapping);

            if ($reason === null) {
                $eligible->push($mapping);
            } else {
                $skipped[] = ['mapping' => $mapping, 'reason' => $reason];
            }
        }

        return ['eligible' => $eligible, 'skipped' => $skipped];
    }

    /**
     * Why a mapping may not be synced, or null when it may (docs/products/kitchen/production-schedule/prd.md §28.2).
     */
    public function skipReason(BranchEsbCode $mapping): ?string
    {
        return match (true) {
            ! ($mapping->branch?->is_active ?? false) => 'Branch tidak aktif.',
            ! $mapping->branch->hasKitchenBrand() => 'Branch bukan Brand Kitchen.',
            default => $this->incompleteReason($mapping),
        };
    }

    /**
     * Mapping-only checks, without Branch queries, for callers that already scoped the Branch.
     */
    public function incompleteReason(BranchEsbCode $mapping): ?string
    {
        $companyCode = mb_strtoupper(trim((string) $mapping->esb_comcode));

        return match (true) {
            ! $mapping->is_active => 'Mapping ESB tidak aktif.',
            $companyCode === '' => 'Company Code ESB kosong.',
            ! array_key_exists($companyCode, (array) config('esb.core.companies', [])) => 'Company Code ESB belum terdaftar di konfigurasi.',
            (int) $mapping->esb_branch_id <= 0 => 'Branch ID ESB belum terisi.',
            default => null,
        };
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function incrementalRange(): array
    {
        $today = CarbonImmutable::today();

        return [
            $today->subDays((int) config('kitchen.production_orders.incremental_days_back', 7)),
            $today->addDays((int) config('kitchen.production_orders.incremental_days_forward', 14)),
        ];
    }

    /**
     * @param  iterable<BranchEsbCode>  $mappings
     * @return list<array{mapping: BranchEsbCode, date_from: string, date_to: string, key: string}>
     */
    public function units(iterable $mappings, EsbProductionOrderSyncMode $mode, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $windowDays = max(1, (int) config('kitchen.production_orders.window_max_days', 31));
        $windows = [];
        for ($start = $from->startOfDay(); $start->lte($to); $start = $start->addDays($windowDays)) {
            $end = $start->addDays($windowDays - 1);
            $windows[] = [$start->toDateString(), ($end->gt($to) ? $to : $end)->toDateString()];
        }

        $units = [];
        foreach ($mappings as $mapping) {
            foreach ($windows as [$dateFrom, $dateTo]) {
                $units[] = [
                    'mapping' => $mapping,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'key' => EsbProductionOrderSyncRun::windowKey($mapping, $mode, $dateFrom, $dateTo),
                ];
            }
        }

        return $units;
    }

    /**
     * @param  list<array{mapping: BranchEsbCode, date_from: string, date_to: string, key: string}>  $units
     * @return array{dispatched: int, already_active: int}
     */
    public function dispatch(array $units, EsbProductionOrderSyncMode $mode): array
    {
        $dispatched = 0;
        $alreadyActive = 0;

        foreach ($units as $unit) {
            $this->closeStuckRun($unit['key']);

            try {
                $run = EsbProductionOrderSyncRun::query()->create([
                    'idempotency_key' => $unit['key'],
                    'active_key' => $unit['key'],
                    'branch_esb_code_id' => $unit['mapping']->id,
                    'branch_id' => $unit['mapping']->branch_id,
                    'company_code' => mb_strtoupper(trim((string) $unit['mapping']->esb_comcode)),
                    'esb_branch_id' => $unit['mapping']->esb_branch_id,
                    'mode' => $mode,
                    'date_from' => $unit['date_from'],
                    'date_to' => $unit['date_to'],
                    'status' => EsbProductionOrderSyncStatus::Queued,
                ]);
            } catch (UniqueConstraintViolationException) {
                $alreadyActive++;

                continue;
            }

            SyncEsbProductionOrdersJob::dispatch($run->id);
            $dispatched++;
        }

        return ['dispatched' => $dispatched, 'already_active' => $alreadyActive];
    }

    private function closeStuckRun(string $key): void
    {
        EsbProductionOrderSyncRun::query()
            ->where('active_key', $key)
            ->where('updated_at', '<', now()->subMinutes((int) config('kitchen.production_orders.stuck_run_minutes', 120)))
            ->get()
            ->each(fn (EsbProductionOrderSyncRun $run) => $run->markFailed('stuck', null, 'Sinkronisasi terhenti tanpa hasil dan dijadwalkan ulang.'));
    }
}
