<?php

namespace App\Console\Commands;

use App\Enums\EsbProductionOrderSyncMode;
use App\Services\Kitchen\ProductionOrderSyncPlanner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Incremental Production Order sync (docs/products/kitchen/production-schedule/prd.md §30.6): today −7 to +14
 * days for every eligible Kitchen mapping. It only dispatches; every ESB read runs in a queued
 * job. The scheduler runs it only while `kitchen.production_orders.sync_enabled` is on; a manual run
 * ignores that toggle so the rollout can sync one mapping first. It never backfills.
 */
#[Signature('kitchen:sync-production-orders
    {--branch=* : Branch ID(s); default every eligible Kitchen Branch}
    {--mapping=* : Branch ESB mapping ID(s)}
    {--dry-run : List the units and skipped mappings without dispatching}')]
#[Description('Dispatch the incremental ESB Production Order sync for Kitchen → Jadwal Produksi.')]
class KitchenSyncProductionOrdersCommand extends Command
{
    public function handle(ProductionOrderSyncPlanner $planner): int
    {
        [$from, $to] = $planner->incrementalRange();
        $mappings = $planner->mappings(array_map('intval', (array) $this->option('branch')), array_map('intval', (array) $this->option('mapping')));
        $units = $planner->units($mappings['eligible'], EsbProductionOrderSyncMode::Incremental, $from, $to);

        $this->info(($this->option('dry-run') ? 'DRY RUN (no change)' : 'EXECUTE')." — incremental {$from->toDateString()} → {$to->toDateString()}, {$mappings['eligible']->count()} mapping(s), ".count($units).' unit(s).');
        foreach ($mappings['skipped'] as $skipped) {
            $this->warn("Dilewati: mapping {$skipped['mapping']->id} (Branch {$skipped['mapping']->branch_id}) — {$skipped['reason']}");
        }

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $result = $planner->dispatch($units, EsbProductionOrderSyncMode::Incremental);
        $this->info("Dispatched {$result['dispatched']} sync job(s); {$result['already_active']} window(s) were already running.");

        return self::SUCCESS;
    }
}
