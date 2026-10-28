<?php

namespace App\Actions\Kitchen;

use App\Enums\EsbProductionOrderSyncMode;
use App\Models\BranchEsbCode;
use App\Models\EsbProductionOrder;
use App\Models\EsbProductionOrderSyncRun;
use App\Models\User;
use App\Services\Kitchen\ProductionOrderCalendarQuery;
use App\Services\Kitchen\ProductionOrderSyncPlanner;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * "Sync Data" on the calendar (docs/products/kitchen/production-schedule/prd.md §39): queues the
 * incremental window for the Kitchen Branches in the user's scope through the same planner as the
 * scheduler. It never calls ESB itself. The Branch from the browser is re-checked against the
 * user's Kitchen Branches; a mapping with an active run is refused by `active_key`, and one whose
 * last run is younger than the cooldown is skipped so repeated clicks cannot flood ESB.
 */
class RequestProductionOrderSyncAction
{
    public function __construct(
        private readonly ProductionOrderSyncPlanner $planner,
        private readonly ProductionOrderCalendarQuery $calendar,
    ) {}

    /**
     * @return array{queued: int, already_active: int, cooling_down: int, skipped: int}
     *
     * @throws AuthorizationException
     */
    public function execute(User $actor, ?int $branchId = null): array
    {
        Gate::forUser($actor)->authorize('sync', EsbProductionOrder::class);

        $branchIds = $this->calendar->filterBranches($actor)->where('is_active', true)->modelKeys();
        if ($branchId !== null) {
            if (! in_array($branchId, $branchIds, true)) {
                throw new AuthorizationException('Branch ini tidak dapat disinkronkan.');
            }

            $branchIds = [$branchId];
        }

        if ($branchIds === []) {
            return ['queued' => 0, 'already_active' => 0, 'cooling_down' => 0, 'skipped' => 0];
        }

        $plan = $this->planner->mappings($branchIds);
        $coolingDown = EsbProductionOrderSyncRun::query()
            ->whereIn('branch_esb_code_id', $plan['eligible']->modelKeys())
            ->where('created_at', '>=', now()->subMinutes((int) config('kitchen.production_orders.manual_sync_cooldown_minutes', 5)))
            ->distinct()
            ->pluck('branch_esb_code_id')
            ->all();
        $ready = $plan['eligible']->reject(fn (BranchEsbCode $mapping): bool => in_array($mapping->id, $coolingDown, true));

        $result = $this->planner->dispatch(
            $this->planner->units($ready, EsbProductionOrderSyncMode::Incremental, ...$this->planner->incrementalRange()),
            EsbProductionOrderSyncMode::Incremental,
        );

        Log::info('Kitchen Production Order manual sync requested.', [
            'user_id' => $actor->id, 'branch_ids' => $branchIds, 'queued' => $result['dispatched'],
            'already_active' => $result['already_active'], 'cooling_down' => count($coolingDown), 'skipped' => count($plan['skipped']),
        ]);

        return [
            'queued' => $result['dispatched'],
            'already_active' => $result['already_active'],
            'cooling_down' => count($coolingDown),
            'skipped' => count($plan['skipped']),
        ];
    }
}
