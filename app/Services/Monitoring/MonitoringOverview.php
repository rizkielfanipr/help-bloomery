<?php

namespace App\Services\Monitoring;

use App\Enums\MonitoringServiceStatus;
use App\Enums\MonitoringTransactionStatus;
use App\Models\MonitoringAlert;
use App\Models\MonitoringCheckResult;
use App\Models\MonitoringDailyAggregate;
use App\Models\MonitoringDeployment;
use App\Models\MonitoringError;
use App\Models\MonitoringIncident;
use App\Models\MonitoringService;
use App\Models\MonitoringTransaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Read model for the Overview page (docs/system-monitoring-prd.md §9). It only reads local
 * monitoring tables — never ESB, GitHub, shell commands, or log files — and is cached briefly.
 */
class MonitoringOverview
{
    public function __construct(
        private readonly MonitoringHeartbeats $heartbeats,
        private readonly MonitoringSettings $settings,
        private readonly MonitoringAggregator $aggregator,
    ) {}

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return Cache::remember(
            'monitoring.overview.'.MonitoringContext::environment(),
            now()->addSeconds((int) config('monitoring.overview_cache_seconds', 30)),
            fn (): array => $this->build(),
        );
    }

    public function forget(): void
    {
        Cache::forget('monitoring.overview.'.MonitoringContext::environment());
    }

    /** @return array<string, mixed> */
    private function build(): array
    {
        $environment = MonitoringContext::environment();

        return [
            'generated_at' => now()->toIso8601String(),
            'environment' => $environment,
            'maintenance' => $this->settings->maintenanceWindow(),
            'in_maintenance' => $this->settings->inMaintenance(),
            'services' => $this->services(),
            'heartbeats' => [
                'scheduler' => $this->heartbeat(MonitoringHeartbeats::SCHEDULER, (int) $this->settings->get('heartbeats.scheduler_late_seconds', 180)),
                'queue' => $this->heartbeat(MonitoringHeartbeats::QUEUE, (int) $this->settings->get('heartbeats.queue_late_seconds', 300)),
            ],
            'transactions' => $this->transactions($environment),
            'errors' => $this->errors($environment),
            'incidents' => MonitoringIncident::query()->forCurrentEnvironment()->active()
                ->orderBy('severity')->latest('detected_at')->limit(5)
                ->get(['id', 'uuid', 'number', 'title', 'severity', 'status', 'detected_at'])
                ->map(fn (MonitoringIncident $incident): array => [
                    'uuid' => $incident->uuid, 'number' => $incident->number, 'title' => $incident->title,
                    'severity' => $incident->severity->getLabel(), 'severity_color' => $incident->severity->getColor(),
                    'status' => $incident->status->getLabel(), 'detected_at' => $incident->detected_at->toIso8601String(),
                ])->all(),
            'open_incident_count' => MonitoringIncident::query()->forCurrentEnvironment()->active()->count(),
            'alerts' => MonitoringAlert::query()->forCurrentEnvironment()->active()->latest('opened_at')->limit(10)
                ->get(['id', 'title', 'severity', 'safe_message', 'opened_at', 'acknowledged_at'])
                ->map(fn (MonitoringAlert $alert): array => [
                    'id' => $alert->id, 'title' => $alert->title, 'severity' => $alert->severity->getLabel(), 'severity_color' => $alert->severity->getColor(),
                    'message' => $alert->safe_message, 'opened_at' => $alert->opened_at->toIso8601String(), 'acknowledged' => $alert->acknowledged_at !== null,
                ])->all(),
            'deployment' => $this->latestDeployment(),
            'trend_24h' => $this->trend24h($environment),
            'trend_7d' => $this->trend7d($environment),
            'uptime_7d' => $this->aggregator->uptime(7),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function services(): array
    {
        return MonitoringService::query()->forCurrentEnvironment()->orderByDesc('is_critical')->orderBy('group')->orderBy('name')
            ->get()
            ->map(function (MonitoringService $service): array {
                $status = $service->last_status ?? MonitoringServiceStatus::Unknown;
                $stale = $service->is_enabled && $service->isStale() && $status !== MonitoringServiceStatus::Maintenance;
                $notConfigured = (bool) ($service->metadata['not_configured'] ?? false);

                return [
                    'key' => $service->key,
                    'name' => $service->name,
                    'group' => $service->group,
                    'critical' => $service->is_critical,
                    'enabled' => $service->is_enabled,
                    'status' => $stale ? MonitoringServiceStatus::Unknown->value : $status->value,
                    'status_label' => match (true) {
                        ! $service->is_enabled => 'Nonaktif',
                        $stale => 'Unknown (data lama)',
                        $notConfigured => 'Not Configured',
                        default => $status->getLabel(),
                    },
                    'color' => $stale ? 'gray' : $status->getColor(),
                    'icon' => ($stale ? MonitoringServiceStatus::Unknown : $status)->icon(),
                    'message' => $service->last_safe_message,
                    'response_time_ms' => $service->last_response_time_ms,
                    'last_checked_at' => $service->last_checked_at?->toIso8601String(),
                    'since' => $service->status_changed_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    /** @return array{status: string, label: string, color: string, age_seconds: ?int, last_beat_at: ?string} */
    private function heartbeat(string $key, int $lateSeconds): array
    {
        $beat = $this->heartbeats->last($key);
        $age = $beat ? (int) max(0, $beat->last_beat_at->diffInSeconds(now())) : null;

        return [
            'status' => match (true) {
                $age === null => 'unknown',
                $age <= $lateSeconds => 'healthy',
                default => 'late',
            },
            'label' => match (true) {
                $age === null => 'Belum pernah tercatat',
                $age <= $lateSeconds => 'Tepat waktu',
                default => 'Terlambat',
            },
            'color' => match (true) {
                $age === null => 'gray',
                $age <= $lateSeconds => 'success',
                default => 'danger',
            },
            'age_seconds' => $age,
            'last_beat_at' => $beat?->last_beat_at->toIso8601String(),
        ];
    }

    /** @return array<string, int> */
    private function transactions(string $environment): array
    {
        $open = [MonitoringTransactionStatus::Failed->value, MonitoringTransactionStatus::Unknown->value, MonitoringTransactionStatus::Partial->value];
        $row = MonitoringTransaction::query()->parents()->where('environment', $environment)
            ->where('started_at', '>=', now()->subDays(30))
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as failed', [MonitoringTransactionStatus::Failed->value])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as unknown', [MonitoringTransactionStatus::Unknown->value])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as partial', [MonitoringTransactionStatus::Partial->value])
            ->selectRaw('sum(case when started_at >= ? then 1 else 0 end) as last_24h', [now()->subDay()])
            ->selectRaw('sum(case when started_at >= ? and status in (?, ?, ?) then 1 else 0 end) as problems_24h', [now()->subDay(), ...$open])
            ->first();

        return [
            'failed' => (int) ($row->failed ?? 0),
            'unknown' => (int) ($row->unknown ?? 0),
            'partial' => (int) ($row->partial ?? 0),
            'last_24h' => (int) ($row->last_24h ?? 0),
            'problems_24h' => (int) ($row->problems_24h ?? 0),
        ];
    }

    /** @return array<string, int> */
    private function errors(string $environment): array
    {
        $row = MonitoringError::query()->where('environment', $environment)->where('last_seen_at', '>=', now()->subDay())
            ->selectRaw('count(*) as last_24h')
            ->selectRaw('sum(case when last_seen_at >= ? then 1 else 0 end) as last_1h', [now()->subHour()])
            ->selectRaw("sum(case when status = 'new' then 1 else 0 end) as untriaged")
            ->first();

        return ['last_1h' => (int) ($row->last_1h ?? 0), 'last_24h' => (int) ($row->last_24h ?? 0), 'untriaged' => (int) ($row->untriaged ?? 0)];
    }

    /** @return array<string, mixed>|null */
    private function latestDeployment(): ?array
    {
        $deployment = MonitoringDeployment::query()->where('environment', 'production')->latest('started_at')->first()
            ?? MonitoringDeployment::query()->forCurrentEnvironment()->latest('started_at')->first();

        return $deployment ? [
            'uuid' => $deployment->uuid,
            'environment' => $deployment->environment,
            'status' => $deployment->status->getLabel(),
            'color' => $deployment->status->getColor(),
            'commit' => $deployment->shortSha(),
            'branch' => $deployment->branch,
            'health' => $deployment->health_status,
            'started_at' => $deployment->started_at?->toIso8601String(),
            'long_running' => $deployment->isLongRunning(),
        ] : null;
    }

    /**
     * Hourly buckets grouped in SQL; timestamps are stored in the application timezone.
     *
     * @return list<array{label: string, total: int, problems: int}>
     */
    private function trend24h(string $environment): array
    {
        $hourExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m-%d %H', monitoring_check_results.checked_at)"
            : "DATE_FORMAT(monitoring_check_results.checked_at, '%Y-%m-%d %H')";

        $rows = MonitoringCheckResult::query()
            ->join('monitoring_services', 'monitoring_services.id', '=', 'monitoring_check_results.monitoring_service_id')
            ->where('monitoring_services.environment', $environment)
            ->where('monitoring_check_results.checked_at', '>=', now()->subHours(24))
            ->groupByRaw($hourExpression)
            ->selectRaw("{$hourExpression} as hour_bucket, count(*) as total, sum(case when monitoring_check_results.status in ('degraded', 'down') then 1 else 0 end) as problems")
            ->toBase()
            ->get()
            ->keyBy('hour_bucket');

        return collect(range(23, 0))->map(function (int $hoursAgo) use ($rows): array {
            $hour = now()->subHours($hoursAgo);
            $bucket = $rows->get($hour->format('Y-m-d H'));

            return ['label' => $hour->format('H:00'), 'total' => (int) ($bucket->total ?? 0), 'problems' => (int) ($bucket->problems ?? 0)];
        })->all();
    }

    /** @return list<array{label: string, total: int, problems: int, transaction_problems: int}> */
    private function trend7d(string $environment): array
    {
        $aggregates = MonitoringDailyAggregate::query()->where('environment', $environment)
            ->whereIn('metric', ['check', 'transaction'])
            ->where('date', '>=', now()->subDays(6)->toDateString())
            ->get(['date', 'metric', 'total', 'problems'])
            ->groupBy('date');

        return collect(range(6, 0))->map(function (int $daysAgo) use ($aggregates): array {
            $day = now()->subDays($daysAgo);
            $rows = $aggregates->get($day->toDateString(), collect());

            return [
                'label' => $day->translatedFormat('d M'),
                'total' => (int) $rows->where('metric', 'check')->sum('total'),
                'problems' => (int) $rows->where('metric', 'check')->sum('problems'),
                'transaction_problems' => (int) $rows->where('metric', 'transaction')->sum('problems'),
            ];
        })->all();
    }
}
