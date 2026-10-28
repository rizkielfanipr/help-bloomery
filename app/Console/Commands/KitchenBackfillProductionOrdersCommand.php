<?php

namespace App\Console\Commands;

use App\Enums\EsbProductionOrderSyncMode;
use App\Services\Kitchen\ProductionOrderSyncPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * Explicit Production Order backfill (docs/products/kitchen/production-schedule/prd.md §30.6, K1). Dry-run by
 * default: it lists the units and skipped mappings and writes nothing. `--execute` dispatches one
 * job per mapping and window. Ranges above `backfill_max_days` need `--allow-long-range`. A
 * backfill never deletes snapshots and never runs from the scheduler.
 */
#[Signature('kitchen:backfill-production-orders
    {--from= : First production date (Y-m-d)}
    {--to= : Last production date (Y-m-d)}
    {--branch=* : Branch ID(s); default every eligible Kitchen Branch}
    {--mapping=* : Branch ESB mapping ID(s)}
    {--execute : Dispatch the jobs (default is a dry-run)}
    {--force : Skip the confirmation prompt}
    {--allow-long-range : Allow a range longer than the configured maximum}')]
#[Description('Dry-run or dispatch the ESB Production Order backfill for Kitchen → Jadwal Produksi.')]
class KitchenBackfillProductionOrdersCommand extends Command
{
    public function handle(ProductionOrderSyncPlanner $planner): int
    {
        if (! $this->option('from') || ! $this->option('to')) {
            $this->error('--from dan --to wajib diisi.');

            return self::FAILURE;
        }

        try {
            $from = CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->option('from'));
            $to = CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->option('to'));
        } catch (Throwable) {
            $from = $to = false;
        }

        if (! $from || ! $to || $from->gt($to)) {
            $this->error('Rentang tidak valid: gunakan format Y-m-d dan --from ≤ --to.');

            return self::FAILURE;
        }

        $maxDays = (int) config('kitchen.production_orders.backfill_max_days', 120);
        $days = (int) $from->diffInDays($to) + 1;
        if ($days > $maxDays && ! $this->option('allow-long-range')) {
            $this->error("Rentang {$days} hari melebihi batas {$maxDays} hari. Tambahkan --allow-long-range bila memang disengaja.");

            return self::FAILURE;
        }

        $mappings = $planner->mappings(array_map('intval', (array) $this->option('branch')), array_map('intval', (array) $this->option('mapping')));
        $units = $planner->units($mappings['eligible'], EsbProductionOrderSyncMode::Backfill, $from, $to);

        $this->info(($this->option('execute') ? 'EXECUTE' : 'DRY RUN (no change)')." — backfill {$from->toDateString()} → {$to->toDateString()} ({$days} hari), {$mappings['eligible']->count()} mapping(s), ".count($units).' unit(s).');
        $this->table(['Mapping', 'Branch', 'Company', 'ESB Branch ID', 'Window'], array_map(fn (array $unit): array => [
            $unit['mapping']->id, $unit['mapping']->branch?->name, $unit['mapping']->esb_comcode, $unit['mapping']->esb_branch_id, "{$unit['date_from']} → {$unit['date_to']}",
        ], $units));
        foreach ($mappings['skipped'] as $skipped) {
            $this->warn("Dilewati: mapping {$skipped['mapping']->id} (Branch {$skipped['mapping']->branch_id}) — {$skipped['reason']}");
        }

        if (! $this->option('execute')) {
            return self::SUCCESS;
        }
        if (! $this->option('force') && ! $this->confirm('Dispatch the backfill jobs?')) {
            $this->warn('Cancelled. Nothing dispatched.');

            return self::FAILURE;
        }

        $result = $planner->dispatch($units, EsbProductionOrderSyncMode::Backfill);
        $this->info("Dispatched {$result['dispatched']} sync job(s); {$result['already_active']} window(s) were already running.");

        return self::SUCCESS;
    }
}
