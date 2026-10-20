<?php

namespace App\Console\Commands\Monitoring;

use App\Jobs\Monitoring\RecordQueueHeartbeatJob;
use App\Services\Monitoring\AlertManager;
use App\Services\Monitoring\HealthCheckRunner;
use App\Services\Monitoring\MonitoringHeartbeats;
use Illuminate\Console\Command;
use Throwable;

/**
 * Scheduled every minute: records the scheduler heartbeat, dispatches the queue heartbeat job,
 * runs due read-only checks, and sends reminders for unacknowledged alerts.
 */
class RunMonitoringChecksCommand extends Command
{
    protected $signature = 'monitoring:run-checks {--force : Run every enabled check regardless of its interval}';

    protected $description = 'Record monitoring heartbeats and run due service health checks';

    public function handle(MonitoringHeartbeats $heartbeats, HealthCheckRunner $runner, AlertManager $alerts): int
    {
        $heartbeats->beat(MonitoringHeartbeats::SCHEDULER);

        try {
            RecordQueueHeartbeatJob::dispatch(now()->toIso8601String());
        } catch (Throwable) {
            $this->components->warn('Queue heartbeat job could not be dispatched.');
        }

        $results = $runner->runDue((bool) $this->option('force'));
        $reminders = $alerts->sendReminders();

        $this->components->info(sprintf('%d check(s) executed, %d reminder(s) sent.', count($results), $reminders));

        return self::SUCCESS;
    }
}
