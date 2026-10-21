<?php

namespace App\Console\Commands;

use App\Enums\OrderingSource;
use App\Enums\OrderingSyncStatus;
use App\Models\Branch;
use App\Services\Ordering\OrderingSyncPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Ordering Recommendation backfill (docs/ordering-recommendation-prd.md §12.4). Dry-run by
 * default: it only lists the units and their checkpoint status and writes nothing. `--execute`
 * dispatches the units that are not completed yet (resume), one chain per branch spaced by
 * `--delay` seconds, and never deletes a valid snapshot.
 */
#[Signature('ordering:backfill
    {--branch=* : Branch ID(s); default every branch with an active ESB mapping}
    {--from= : First day (Y-m-d)}
    {--to= : Last day (Y-m-d), before today}
    {--source=* : daily_usage and/or stock_movement (default both)}
    {--execute : Dispatch the jobs (default is a dry-run)}
    {--skip-failed : Do not retry units whose last run failed}
    {--forecast : Generate the forecast for --to after the snapshot}
    {--delay=0 : Seconds between branch chains (rate limit)}
    {--force : Skip the confirmation prompt}')]
#[Description('Dry-run or dispatch the Ordering Recommendation history backfill.')]
class OrderingBackfillCommand extends Command
{
    private const MAX_DAYS = 120;

    public function handle(OrderingSyncPlanner $planner): int
    {
        try {
            $from = CarbonImmutable::parse((string) $this->option('from'))->startOfDay();
            $to = CarbonImmutable::parse((string) $this->option('to'))->startOfDay();
        } catch (\Throwable) {
            $this->error('--from dan --to wajib berupa tanggal yang valid.');

            return self::FAILURE;
        }
        if (! $this->option('from') || ! $this->option('to') || $from->gt($to) || ! $to->lt(CarbonImmutable::today()) || $from->diffInDays($to) + 1 > self::MAX_DAYS) {
            $this->error('Rentang tidak valid: --from ≤ --to, --to sebelum hari ini, maksimal '.self::MAX_DAYS.' hari.');

            return self::FAILURE;
        }

        $sources = collect($this->option('source'))->map(fn (string $source): ?OrderingSource => OrderingSource::tryFrom($source));
        if ($sources->contains(null)) {
            $this->error('Source tidak dikenal. Gunakan daily_usage atau stock_movement.');

            return self::FAILURE;
        }
        $sources = $sources->isEmpty() ? OrderingSource::cases() : $sources->all();

        $branchIds = array_map('intval', (array) $this->option('branch'));
        $branches = Branch::query()->with('esbCodes')
            ->whereHas('esbCodes', fn ($query) => $query->where('is_active', true))
            ->when($branchIds !== [], fn ($query) => $query->whereKey($branchIds))
            ->get();
        if ($branchIds !== [] && $branches->count() !== count(array_unique($branchIds))) {
            $this->error('Branch tidak ditemukan atau belum memiliki mapping ESB aktif: '.implode(', ', array_diff($branchIds, $branches->modelKeys())));

            return self::FAILURE;
        }

        $units = $planner->plan($branches, $from->toDateString(), $to->toDateString(), $sources);
        $byStatus = collect($units)->countBy(fn (array $unit): string => $unit['status']?->value ?? 'not_started');
        $this->info(($this->option('execute') ? 'EXECUTE' : 'DRY RUN (no change)')." — {$branches->count()} branch(es), {$from->toDateString()} → {$to->toDateString()}");
        $this->table(['Checkpoint', 'Units'], $byStatus->map(fn (int $count, string $status): array => [$status, $count])->values()->all());
        foreach (collect($units)->where('status', OrderingSyncStatus::Failed)->take(20) as $unit) {
            $this->warn("Failed earlier: branch {$unit['branch']->id} {$unit['company_code']}/{$unit['esb_branch_code']} {$unit['source']->value} {$unit['date']}");
        }

        if (! $this->option('execute')) {
            return self::SUCCESS;
        }
        if (! $this->option('force') && ! $this->confirm('Dispatch the backfill jobs?')) {
            $this->warn('Cancelled. Nothing dispatched.');

            return self::FAILURE;
        }

        $dispatched = $planner->dispatch(
            $units,
            $to->toDateString(),
            $this->option('forecast') ? config('ordering_recommendation.horizons') : [],
            max(0, (int) $this->option('delay')),
            ! $this->option('skip-failed'),
        );
        $this->info("Dispatched {$dispatched} sync job(s); completed units were skipped.");

        return self::SUCCESS;
    }
}
