<?php

namespace App\Services\Ordering;

use App\Enums\OrderingSource;
use App\Enums\OrderingSyncStatus;
use App\Jobs\Ordering\GenerateOrderingForecastJob;
use App\Jobs\Ordering\SyncOrderingSourceJob;
use App\Models\Branch;
use App\Models\OrderingSyncRun;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;

/**
 * Plans and dispatches the Ordering Recommendation sync in small units — one source, company
 * pair, and day per job — and chains the forecast after the snapshot of a branch
 * (docs/products/analytics/ordering-recommendation/prd.md §12.2, §12.4). Units whose run already completed are
 * skipped so a backfill can be resumed safely.
 */
class OrderingSyncPlanner
{
    /**
     * @param  Collection<int, Branch>  $branches
     * @param  list<OrderingSource>  $sources
     * @return list<array{branch: Branch, pair_id: int, company_code: string, esb_branch_code: string, source: OrderingSource, date: string, status: ?OrderingSyncStatus}>
     */
    public function plan(Collection $branches, string $from, string $to, array $sources): array
    {
        $units = [];
        foreach ($branches as $branch) {
            foreach ($branch->esbCodes->where('is_active', true) as $pair) {
                foreach (CarbonPeriod::create($from, $to) as $day) {
                    foreach ($sources as $source) {
                        $units[] = [
                            'branch' => $branch,
                            'pair_id' => $pair->id,
                            'company_code' => (string) $pair->esb_comcode,
                            'esb_branch_code' => (string) $pair->esb_branch_code,
                            'source' => $source,
                            'date' => $day->toDateString(),
                            'key' => OrderingSyncRun::idempotencyKey($source, $pair, $day->toDateString()),
                        ];
                    }
                }
            }
        }

        $statuses = OrderingSyncRun::query()->whereIn('idempotency_key', array_column($units, 'key'))->pluck('status', 'idempotency_key');

        return array_map(function (array $unit) use ($statuses): array {
            $unit['status'] = $statuses[$unit['key']] ?? null;
            unset($unit['key']);

            return $unit;
        }, $units);
    }

    /**
     * Dispatches pending units per branch as one chain ending with the forecast runs, spacing
     * branch chains by `$delaySeconds` to limit ESB load.
     *
     * @param  list<array<string, mixed>>  $units
     * @param  list<int>  $horizons
     */
    public function dispatch(array $units, string $cutoff, array $horizons, int $delaySeconds = 0, bool $retryFailed = true): int
    {
        $dispatched = 0;
        $position = 0;
        foreach (collect($units)->groupBy(fn (array $unit): int => $unit['branch']->id) as $branchUnits) {
            $jobs = $branchUnits
                ->filter(fn (array $unit): bool => $unit['status'] !== OrderingSyncStatus::Completed && ($retryFailed || $unit['status'] !== OrderingSyncStatus::Failed))
                ->map(fn (array $unit): SyncOrderingSourceJob => new SyncOrderingSourceJob($unit['source']->value, $unit['pair_id'], $unit['date']))
                ->values()->all();
            $dispatched += count($jobs);
            $branchId = $branchUnits->first()['branch']->id;
            foreach ($horizons as $horizon) {
                $jobs[] = new GenerateOrderingForecastJob($branchId, $cutoff, $horizon);
            }

            Bus::chain($jobs)->delay(now()->addSeconds($delaySeconds * $position++))->dispatch();
        }

        return $dispatched;
    }
}
