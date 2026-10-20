<?php

namespace App\Services\Monitoring;

use App\Enums\MonitoringServiceStatus;
use App\Models\MonitoringCheckResult;
use App\Models\MonitoringService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs due checks, stores the sanitized result, and hands the observation to the state
 * evaluator. A failing check never throws out of the runner.
 */
class HealthCheckRunner
{
    public function __construct(
        private readonly MonitoringServiceCatalog $catalog,
        private readonly ServiceStateEvaluator $evaluator,
        private readonly MonitoringSanitizer $sanitizer,
    ) {}

    /** @return array<string, string> service key => effective status */
    public function runDue(bool $force = false): array
    {
        $results = [];
        foreach ($this->catalog->sync() as $service) {
            if (! $service->is_enabled || (! $force && ! $this->isDue($service))) {
                continue;
            }

            $results[$service->key] = $this->run($service)->value;
        }

        return $results;
    }

    public function run(MonitoringService $service): MonitoringServiceStatus
    {
        $check = $this->catalog->check($service);
        $started = microtime(true);

        try {
            $result = $check
                ? $check->run($service)
                : HealthCheckResult::notConfigured('Implementasi pemeriksaan belum tersedia.');
        } catch (Throwable $exception) {
            Log::warning('Monitoring health check crashed.', ['service' => $service->key, 'exception' => $exception::class]);
            $result = new HealthCheckResult(MonitoringServiceStatus::Down, 'Pemeriksaan gagal dijalankan.');
        }

        $result = $result->withTiming((int) round((microtime(true) - $started) * 1000));
        $message = (string) $this->sanitizer->message($result->message, 500);
        $metadata = (array) $this->sanitizer->sanitize($result->metadata);

        MonitoringCheckResult::query()->create([
            'monitoring_service_id' => $service->id,
            'status' => $result->status,
            'checked_at' => now(),
            'response_time_ms' => $result->responseTimeMs,
            'http_status' => $result->httpStatus,
            'safe_message' => $message,
            'metadata' => $metadata,
        ]);

        return $this->evaluator->apply($service, $result->status, $message, $result->responseTimeMs, $result->httpStatus, $metadata);
    }

    /**
     * External checks back off while a service keeps failing so an outage is not hammered.
     */
    public function isDue(MonitoringService $service): bool
    {
        if ($service->last_checked_at === null) {
            return true;
        }

        $multiplier = 1;
        if ($service->group === 'esb' && $service->failure_threshold > 0) {
            $multiplier = match (true) {
                $service->consecutive_failures >= $service->failure_threshold * 4 => 4,
                $service->consecutive_failures >= $service->failure_threshold * 2 => 2,
                default => 1,
            };
        }

        return $service->last_checked_at->copy()->addSeconds(max(1, $service->check_interval_seconds * $multiplier - 5))->isPast();
    }
}
