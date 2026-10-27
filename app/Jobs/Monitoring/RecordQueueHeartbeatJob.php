<?php

namespace App\Jobs\Monitoring;

use App\Services\Monitoring\MonitoringHeartbeats;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Proves a queue worker is processing jobs (docs/operations/monitoring/prd.md §12.1). Idempotent,
 * single try, and failure-tolerant so it never blocks business jobs.
 */
class RecordQueueHeartbeatJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 15;

    public function __construct(public readonly string $dispatchedAt)
    {
        if ($queue = config('monitoring.queue.heartbeat_queue')) {
            $this->onQueue($queue);
        }
    }

    public function handle(MonitoringHeartbeats $heartbeats): void
    {
        $heartbeats->beat(MonitoringHeartbeats::QUEUE, [
            'dispatched_at' => $this->dispatchedAt,
            'connection' => config('queue.default'),
        ]);
    }
}
