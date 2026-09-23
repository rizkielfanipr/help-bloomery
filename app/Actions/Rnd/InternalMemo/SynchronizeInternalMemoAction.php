<?php

namespace App\Actions\Rnd\InternalMemo;

use App\Enums\RndInternalMemoMenuSyncStatus;
use App\Enums\RndInternalMemoSyncRunStatus;
use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoSyncRun;
use App\Models\User;
use App\Services\Rnd\InternalMemo\InternalMemoBomResolver;
use App\Services\Rnd\InternalMemo\InternalMemoForecastCalculator;
use Throwable;

/**
 * docs/rnd-internal-memo-prd.md §7.4, §17. Resolves the BOM of every Menu on the memo one at a
 * time; one Menu's failure does not stop the others. The caller is responsible for the Draft/
 * NeedsAttention/Ready permission guard (RndInternalMemoPolicy::sync) and for setting the memo
 * to Syncing before dispatching, so the UI can block a second submission immediately.
 */
class SynchronizeInternalMemoAction
{
    public function __construct(
        private InternalMemoBomResolver $resolver,
        private InternalMemoForecastCalculator $forecastCalculator,
        private RecalculateInternalMemoStatusAction $recalculateStatus,
    ) {}

    public function execute(RndInternalMemo $memo, User $actor): RndInternalMemoSyncRun
    {
        $syncRun = $memo->syncRuns()->create([
            'company_code' => $memo->company_code,
            'status' => RndInternalMemoSyncRunStatus::Running,
            'started_at' => now(),
            'triggered_by' => $actor->id,
        ]);

        $requestCount = 0;
        $errorCount = 0;
        $errorMessages = [];

        foreach ($memo->menus()->get() as $menu) {
            $menu->update(['sync_status' => RndInternalMemoMenuSyncStatus::Syncing]);

            try {
                $result = $this->resolver->resolve($menu);
                $requestCount++;

                $menu->update([
                    'sync_status' => RndInternalMemoMenuSyncStatus::Synced,
                    'synced_at' => now(),
                    'sync_error' => $result['blockers'] === [] ? null : implode(' | ', $result['blockers']),
                    'sync_warnings' => $result['warnings'] === [] ? null : $result['warnings'],
                    'bom_snapshot' => $result['bom_snapshot'],
                ]);

                // Materials were just replaced (fresh quantity_per_menu, net_quantity reset to
                // 0 by the resolver); recompute net_quantity against the Menu's existing
                // Forecast Quantity right away instead of leaving it at 0 until the next edit.
                $this->forecastCalculator->recalculateMenu($menu);
            } catch (Throwable $exception) {
                $errorCount++;
                $menu->update([
                    'sync_status' => RndInternalMemoMenuSyncStatus::Failed,
                    'sync_error' => $exception->getMessage(),
                ]);
                $errorMessages[] = "{$menu->menu_name}: {$exception->getMessage()}";
            }
        }

        $syncRun->update([
            'status' => $errorCount > 0 ? RndInternalMemoSyncRunStatus::Failed : RndInternalMemoSyncRunStatus::Completed,
            'finished_at' => now(),
            'request_count' => $requestCount,
            'error_count' => $errorCount,
            'error_summary' => $errorMessages === [] ? null : implode(' | ', $errorMessages),
        ]);

        $memo->update(['source_synced_at' => now()]);
        $this->recalculateStatus->execute($memo);

        return $syncRun->fresh();
    }
}
