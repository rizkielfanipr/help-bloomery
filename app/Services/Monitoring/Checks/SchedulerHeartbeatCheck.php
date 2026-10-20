<?php

namespace App\Services\Monitoring\Checks;

use App\Enums\MonitoringServiceStatus;
use App\Models\MonitoringService;
use App\Services\Monitoring\HealthCheckResult;
use App\Services\Monitoring\MonitoringHeartbeats;
use App\Services\Monitoring\MonitoringSettings;
use Carbon\CarbonImmutable;

/**
 * The check itself runs inside the scheduler, so it inspects the gap before the current beat:
 * a long gap means the scheduler was late or stopped. A scheduler that stopped completely is
 * detected on the dashboard from the heartbeat age (see MonitoringOverview).
 */
class SchedulerHeartbeatCheck implements HealthCheck
{
    public function __construct(private readonly MonitoringHeartbeats $heartbeats, private readonly MonitoringSettings $settings) {}

    public function run(MonitoringService $service): HealthCheckResult
    {
        $heartbeat = $this->heartbeats->last(MonitoringHeartbeats::SCHEDULER);
        if (! $heartbeat) {
            return HealthCheckResult::notConfigured('Heartbeat scheduler belum pernah tercatat.');
        }

        $late = (int) $this->settings->get('heartbeats.scheduler_late_seconds', 180);
        $previous = $heartbeat->metadata['previous_beat_at'] ?? null;
        $gap = $previous ? (int) CarbonImmutable::parse($previous)->diffInSeconds($heartbeat->last_beat_at) : null;

        if ($gap !== null && $gap > $late) {
            return new HealthCheckResult(MonitoringServiceStatus::Degraded, "Scheduler sempat terlambat {$gap} detik sebelum heartbeat terakhir.", metadata: ['gap_seconds' => $gap]);
        }

        return HealthCheckResult::healthy('Scheduler berjalan sesuai jadwal.', ['gap_seconds' => $gap]);
    }
}
