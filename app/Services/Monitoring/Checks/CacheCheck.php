<?php

namespace App\Services\Monitoring\Checks;

use App\Enums\MonitoringServiceStatus;
use App\Models\MonitoringService;
use App\Services\Monitoring\HealthCheckResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes, reads, and removes one namespaced temporary key.
 */
class CacheCheck implements HealthCheck
{
    public function run(MonitoringService $service): HealthCheckResult
    {
        $key = 'monitoring:probe:'.Str::uuid();
        $value = Str::random(16);

        try {
            Cache::put($key, $value, 60);
            $readBack = Cache::get($key);
            Cache::forget($key);
        } catch (Throwable) {
            return new HealthCheckResult(MonitoringServiceStatus::Down, 'Cache tidak dapat ditulis atau dibaca.');
        }

        return $readBack === $value
            ? HealthCheckResult::healthy('Cache dapat ditulis dan dibaca.', ['store' => config('cache.default')])
            : new HealthCheckResult(MonitoringServiceStatus::Degraded, 'Nilai cache tidak terbaca kembali.', metadata: ['store' => config('cache.default')]);
    }
}
