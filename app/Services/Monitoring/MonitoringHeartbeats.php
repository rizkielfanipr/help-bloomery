<?php

namespace App\Services\Monitoring;

use App\Models\MonitoringHeartbeat;
use Throwable;

/**
 * Local heartbeats written by the scheduler and the queue worker (docs/system-monitoring-prd.md §12).
 */
class MonitoringHeartbeats
{
    public const SCHEDULER = 'scheduler';

    public const QUEUE = 'queue';

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function beat(string $key, array $metadata = []): ?MonitoringHeartbeat
    {
        try {
            $heartbeat = MonitoringHeartbeat::query()->firstOrNew(['environment' => MonitoringContext::environment(), 'key' => $key]);
            $previous = $heartbeat->last_beat_at;
            $heartbeat->fill([
                'last_beat_at' => now(),
                'metadata' => $metadata + ['previous_beat_at' => $previous?->toIso8601String()],
            ])->save();

            return $heartbeat;
        } catch (Throwable) {
            return null;
        }
    }

    public function last(string $key): ?MonitoringHeartbeat
    {
        return MonitoringHeartbeat::query()
            ->where('environment', MonitoringContext::environment())
            ->where('key', $key)
            ->first();
    }

    /**
     * Seconds since the last beat, or null when it never ran in this environment.
     */
    public function ageInSeconds(string $key): ?int
    {
        $beat = $this->last($key)?->last_beat_at;

        return $beat ? (int) max(0, $beat->diffInSeconds(now())) : null;
    }
}
