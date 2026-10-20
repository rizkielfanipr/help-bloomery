<?php

namespace App\Services\Monitoring;

use App\Enums\MonitoringServiceStatus;
use App\Models\MonitoringService;

/**
 * Applies failure/recovery thresholds and the maintenance window to one observation
 * (docs/system-monitoring-prd.md §10.3). Alerts are only evaluated on effective state changes.
 */
class ServiceStateEvaluator
{
    public function __construct(private readonly MonitoringSettings $settings, private readonly AlertManager $alerts) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function apply(MonitoringService $service, MonitoringServiceStatus $observed, string $message, ?int $responseTimeMs, ?int $httpStatus, array $metadata): MonitoringServiceStatus
    {
        $previous = $service->last_status ?? MonitoringServiceStatus::Unknown;
        [$failures, $successes] = match (true) {
            $observed->isProblem() => [$service->consecutive_failures + 1, 0],
            $observed === MonitoringServiceStatus::Healthy => [0, $service->consecutive_successes + 1],
            default => [0, 0],
        };

        $effective = $this->effectiveStatus($service, $previous, $observed, $failures, $successes);
        if ($this->settings->inMaintenance($service->key)) {
            $effective = MonitoringServiceStatus::Maintenance;
        }

        $service->forceFill([
            'last_status' => $effective,
            'last_observed_status' => $observed,
            'last_checked_at' => now(),
            'status_changed_at' => $effective !== $previous ? now() : ($service->status_changed_at ?? now()),
            'last_response_time_ms' => $responseTimeMs,
            'last_http_status' => $httpStatus,
            'last_safe_message' => $message,
            'consecutive_failures' => $failures,
            'consecutive_successes' => $successes,
            'metadata' => $metadata,
        ])->save();

        $this->alerts->evaluateService($service, $previous, $effective);

        return $effective;
    }

    private function effectiveStatus(MonitoringService $service, MonitoringServiceStatus $previous, MonitoringServiceStatus $observed, int $failures, int $successes): MonitoringServiceStatus
    {
        if ($observed->isProblem()) {
            if ($failures >= max(1, $service->failure_threshold)) {
                return $observed;
            }

            return in_array($previous, [MonitoringServiceStatus::Unknown, MonitoringServiceStatus::Maintenance], true) ? MonitoringServiceStatus::Degraded : $previous;
        }

        if ($observed === MonitoringServiceStatus::Healthy && $previous->isProblem() && $successes < max(1, $service->recovery_threshold)) {
            return $previous;
        }

        return $observed;
    }
}
