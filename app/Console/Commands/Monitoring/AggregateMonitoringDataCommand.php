<?php

namespace App\Console\Commands\Monitoring;

use App\Services\Monitoring\MonitoringAggregator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AggregateMonitoringDataCommand extends Command
{
    protected $signature = 'monitoring:aggregate {--date= : Day to aggregate (Y-m-d), default yesterday} {--days=1 : Number of days ending at --date}';

    protected $description = 'Build System Monitoring daily aggregates used by Overview trends';

    public function handle(MonitoringAggregator $aggregator): int
    {
        $end = $this->option('date') ? Carbon::parse((string) $this->option('date')) : now()->subDay();
        $days = max(1, min(400, (int) $this->option('days')));

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $day = $end->copy()->subDays($offset);
            $this->components->twoColumnDetail($day->toDateString(), $aggregator->aggregateDay($day).' row(s)');
        }

        return self::SUCCESS;
    }
}
