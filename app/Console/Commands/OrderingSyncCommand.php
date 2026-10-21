<?php

namespace App\Console\Commands;

use App\Enums\OrderingSource;
use App\Models\Branch;
use App\Services\Ordering\OrderingSyncPlanner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Daily Ordering Recommendation sync (docs/ordering-recommendation-prd.md §12.2): dispatches the
 * previous day's Daily Usage and Stock Movement snapshot per branch and company pair, then the
 * 7- and 14-day forecasts. Only dispatches; every fetch runs in its own queued job.
 */
#[Signature('ordering:sync {--date= : Day to sync (default: yesterday)}')]
#[Description('Dispatch the daily Ordering Recommendation snapshot and forecast jobs.')]
class OrderingSyncCommand extends Command
{
    public function handle(OrderingSyncPlanner $planner): int
    {
        $date = $this->option('date') ?: now()->subDay()->toDateString();
        $branches = Branch::query()->with('esbCodes')->whereHas('esbCodes', fn ($query) => $query->where('is_active', true))->get();
        $units = $planner->plan($branches, $date, $date, OrderingSource::cases());
        $dispatched = $planner->dispatch($units, $date, config('ordering_recommendation.horizons'));

        $this->info("Dispatched {$dispatched} sync job(s) for {$branches->count()} branch(es), cutoff {$date}.");

        return self::SUCCESS;
    }
}
