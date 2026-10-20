<?php

namespace App\Console\Commands\Monitoring;

use App\Services\Monitoring\MonitoringAggregator;
use App\Services\Monitoring\MonitoringRetention;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PruneMonitoringDataCommand extends Command
{
    protected $signature = 'monitoring:prune {--dry-run : Only count what would be removed}';

    protected $description = 'Apply System Monitoring retention (incidents are never removed)';

    public function handle(MonitoringRetention $retention, MonitoringAggregator $aggregator): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if (! $dryRun) {
            // Make sure yesterday's rollup exists before its detail rows can ever be pruned.
            $aggregator->aggregateDay(now()->subDay());
        }

        $summary = $retention->run($dryRun);
        foreach ($summary as $key => $count) {
            $this->components->twoColumnDetail($key, (string) $count);
        }

        Log::info('System monitoring retention '.($dryRun ? 'dry run' : 'completed').'.', $summary);

        return self::SUCCESS;
    }
}
