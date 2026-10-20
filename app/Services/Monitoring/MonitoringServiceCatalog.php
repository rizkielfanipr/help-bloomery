<?php

namespace App\Services\Monitoring;

use App\Models\MonitoringService;
use App\Services\Monitoring\Checks\ApplicationCheck;
use App\Services\Monitoring\Checks\CacheCheck;
use App\Services\Monitoring\Checks\DatabaseCheck;
use App\Services\Monitoring\Checks\DiskSpaceCheck;
use App\Services\Monitoring\Checks\EsbReadCheck;
use App\Services\Monitoring\Checks\HealthCheck;
use App\Services\Monitoring\Checks\ObjectStorageCheck;
use App\Services\Monitoring\Checks\QueueHeartbeatCheck;
use App\Services\Monitoring\Checks\SchedulerHeartbeatCheck;
use App\Services\Monitoring\Checks\SslCertificateCheck;
use Illuminate\Support\Collection;

/**
 * Maps configured check keys to their implementation and keeps one `monitoring_services` row
 * per key for the current environment. Existing rows keep their edited configuration.
 */
class MonitoringServiceCatalog
{
    /** @var array<string, class-string<HealthCheck>> */
    public const CHECKS = [
        'application' => ApplicationCheck::class,
        'database' => DatabaseCheck::class,
        'cache' => CacheCheck::class,
        'queue' => QueueHeartbeatCheck::class,
        'scheduler' => SchedulerHeartbeatCheck::class,
        'object_storage' => ObjectStorageCheck::class,
        'disk_space' => DiskSpaceCheck::class,
        'esb_core' => EsbReadCheck::class,
        'esb_bom' => EsbReadCheck::class,
        'esb_company_core' => EsbReadCheck::class,
        'esb_promotion' => EsbReadCheck::class,
        'ssl_certificate' => SslCertificateCheck::class,
    ];

    /** @return Collection<int, MonitoringService> */
    public function sync(): Collection
    {
        $environment = MonitoringContext::environment();
        $existing = MonitoringService::query()->where('environment', $environment)->get()->keyBy('key');

        foreach ((array) config('monitoring.checks', []) as $key => $definition) {
            if (! isset(self::CHECKS[$key]) || $existing->has($key)) {
                continue;
            }

            $existing->put($key, MonitoringService::query()->create([
                'environment' => $environment,
                'key' => $key,
                'name' => $definition['name'] ?? str($key)->headline()->toString(),
                'group' => $definition['group'] ?? 'core',
                'is_enabled' => (bool) ($definition['enabled'] ?? true),
                'is_critical' => (bool) ($definition['critical'] ?? false),
                'check_interval_seconds' => (int) ($definition['interval_seconds'] ?? 60),
                'timeout_seconds' => (int) ($definition['timeout_seconds'] ?? 5),
                'failure_threshold' => (int) ($definition['failure_threshold'] ?? 2),
                'recovery_threshold' => (int) ($definition['recovery_threshold'] ?? 1),
            ]));
        }

        return $existing->values();
    }

    public function check(MonitoringService $service): ?HealthCheck
    {
        $class = self::CHECKS[$service->key] ?? null;

        return $class ? app($class) : null;
    }
}
