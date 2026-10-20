<?php

namespace App\Services\Monitoring\Checks;

use App\Models\MonitoringService;
use App\Services\Monitoring\HealthCheckResult;

/**
 * One read-only, isolated service check. Implementations must never perform a business mutation
 * and must respect `$service->timeout_seconds`.
 */
interface HealthCheck
{
    public function run(MonitoringService $service): HealthCheckResult;
}
