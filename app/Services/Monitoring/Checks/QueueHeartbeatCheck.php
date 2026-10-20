<?php

namespace App\Services\Monitoring\Checks;

use App\Enums\MonitoringServiceStatus;
use App\Models\MonitoringService;
use App\Services\Monitoring\HealthCheckResult;
use App\Services\Monitoring\MonitoringHeartbeats;
use App\Services\Monitoring\MonitoringSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Reads the age of the last heartbeat processed by a queue worker plus pending/failed counts
 * when the database queue driver makes them observable.
 */
class QueueHeartbeatCheck implements HealthCheck
{
    public function __construct(private readonly MonitoringHeartbeats $heartbeats, private readonly MonitoringSettings $settings) {}

    public function run(MonitoringService $service): HealthCheckResult
    {
        $metadata = $this->queueMetadata();
        $age = $this->heartbeats->ageInSeconds(MonitoringHeartbeats::QUEUE);
        if ($age === null) {
            return new HealthCheckResult(MonitoringServiceStatus::Unknown, 'Heartbeat queue worker belum pernah diproses.', metadata: $metadata);
        }

        $late = (int) $this->settings->get('heartbeats.queue_late_seconds', 300);
        $metadata['heartbeat_age_seconds'] = $age;

        return match (true) {
            $age <= $late => HealthCheckResult::healthy('Queue worker memproses heartbeat.', $metadata),
            $age <= $late * 3 => new HealthCheckResult(MonitoringServiceStatus::Degraded, "Heartbeat queue terlambat {$age} detik.", metadata: $metadata),
            default => new HealthCheckResult(MonitoringServiceStatus::Down, "Queue worker tidak memproses heartbeat selama {$age} detik.", metadata: $metadata),
        };
    }

    /** @return array<string, mixed> */
    private function queueMetadata(): array
    {
        $connection = (string) config('queue.default');
        $metadata = ['connection' => $connection];

        try {
            if (config("queue.connections.{$connection}.driver") === 'database') {
                $table = (string) config("queue.connections.{$connection}.table", 'jobs');
                $metadata['pending_jobs'] = DB::table($table)->count();
                $oldest = DB::table($table)->min('available_at');
                $metadata['oldest_pending_age_seconds'] = $oldest ? max(0, now()->timestamp - (int) $oldest) : null;
            }

            $failedTable = (string) config('queue.failed.table', 'failed_jobs');
            if (Schema::hasTable($failedTable)) {
                $metadata['failed_jobs'] = DB::table($failedTable)->count();
            }
        } catch (Throwable) {
            $metadata['counts_unavailable'] = true;
        }

        return $metadata;
    }
}
