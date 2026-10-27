<?php

namespace App\Services\Monitoring;

use App\Models\MonitoringCheckResult;
use App\Models\MonitoringDailyAggregate;
use App\Models\MonitoringError;
use App\Models\MonitoringService;
use App\Models\MonitoringTransaction;
use Carbon\CarbonInterface;

/**
 * Builds the daily rollups the Overview 7-day trend reads (docs/products/system-monitoring/prd.md §19).
 * Only metrics the dashboard uses are aggregated. Re-running a day overwrites the same rows.
 */
class MonitoringAggregator
{
    public function aggregateDay(CarbonInterface $day): int
    {
        $environment = MonitoringContext::environment();
        [$from, $until] = [$day->copy()->startOfDay(), $day->copy()->endOfDay()];
        $rows = [];

        MonitoringCheckResult::query()
            ->join('monitoring_services', 'monitoring_services.id', '=', 'monitoring_check_results.monitoring_service_id')
            ->where('monitoring_services.environment', $environment)
            ->whereBetween('monitoring_check_results.checked_at', [$from, $until])
            ->groupBy('monitoring_services.key')
            ->selectRaw("monitoring_services.key as dimension, count(*) as total, sum(case when monitoring_check_results.status in ('degraded', 'down') then 1 else 0 end) as problems, avg(monitoring_check_results.response_time_ms) as avg_response_ms")
            ->get()
            ->each(function ($row) use (&$rows): void {
                $rows[] = ['metric' => 'check', 'dimension' => $row->dimension, 'total' => (int) $row->total, 'problems' => (int) $row->problems, 'avg_response_ms' => $row->avg_response_ms !== null ? (int) round((float) $row->avg_response_ms) : null];
            });

        MonitoringTransaction::query()->parents()->where('environment', $environment)
            ->whereBetween('started_at', [$from, $until])
            ->groupBy('status')
            ->selectRaw('status as dimension, count(*) as total')
            ->get()
            ->each(function ($row) use (&$rows): void {
                $status = $row->getRawOriginal('dimension');
                $rows[] = ['metric' => 'transaction', 'dimension' => $status, 'total' => (int) $row->total, 'problems' => in_array($status, ['failed', 'unknown', 'partial'], true) ? (int) $row->total : 0, 'avg_response_ms' => null];
            });

        $newErrors = MonitoringError::query()->where('environment', $environment)->whereBetween('first_seen_at', [$from, $until])->count();
        $rows[] = ['metric' => 'error', 'dimension' => 'new_fingerprints', 'total' => $newErrors, 'problems' => $newErrors, 'avg_response_ms' => null];

        foreach ($rows as $row) {
            MonitoringDailyAggregate::query()->updateOrCreate(
                ['date' => $day->toDateString(), 'environment' => $environment, 'metric' => $row['metric'], 'dimension' => $row['dimension']],
                ['total' => $row['total'], 'problems' => $row['problems'], 'avg_response_ms' => $row['avg_response_ms']],
            );
        }

        return count($rows);
    }

    /**
     * Uptime percentage per service for a period from aggregates plus today's live rows.
     *
     * @return array<string, float|null>
     */
    public function uptime(int $days): array
    {
        $environment = MonitoringContext::environment();
        $totals = MonitoringDailyAggregate::query()->where('environment', $environment)->where('metric', 'check')
            ->where('date', '>=', now()->subDays($days)->toDateString())
            ->where('date', '<', now()->toDateString())
            ->groupBy('dimension')
            ->selectRaw('dimension, sum(total) as total, sum(problems) as problems')
            ->get()
            ->keyBy('dimension');

        return MonitoringService::query()->where('environment', $environment)->pluck('key')
            ->mapWithKeys(function (string $key) use ($totals): array {
                $row = $totals->get($key);

                return [$key => $row && (int) $row->total > 0 ? round(100 - ((int) $row->problems / (int) $row->total * 100), 2) : null];
            })
            ->all();
    }
}
