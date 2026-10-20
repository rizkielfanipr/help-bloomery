<?php

namespace App\Services\Monitoring\Checks;

use App\Enums\MonitoringServiceStatus;
use App\Models\MonitoringService;
use App\Services\Monitoring\HealthCheckResult;
use Illuminate\Support\Facades\DB;
use Throwable;

class DatabaseCheck implements HealthCheck
{
    public function run(MonitoringService $service): HealthCheckResult
    {
        $started = microtime(true);

        try {
            DB::select('select 1');
        } catch (Throwable) {
            return new HealthCheckResult(MonitoringServiceStatus::Down, 'Query database ringan gagal.');
        }

        $elapsed = (int) round((microtime(true) - $started) * 1000);

        return $elapsed > 1000
            ? new HealthCheckResult(MonitoringServiceStatus::Degraded, 'Database lambat merespons.', $elapsed)
            : new HealthCheckResult(MonitoringServiceStatus::Healthy, 'Database merespons normal.', $elapsed, metadata: ['driver' => DB::connection()->getDriverName()]);
    }
}
