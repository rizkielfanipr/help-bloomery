<?php

namespace App\Services\Monitoring\Checks;

use App\Enums\MonitoringServiceStatus;
use App\Models\MonitoringService;
use App\Services\Monitoring\HealthCheckResult;

/**
 * Local disk usage of the application volume via PHP filesystem functions; no shell command.
 */
class DiskSpaceCheck implements HealthCheck
{
    public function run(MonitoringService $service): HealthCheckResult
    {
        $total = @disk_total_space(base_path());
        $free = @disk_free_space(base_path());
        if (! $total || $free === false) {
            return HealthCheckResult::notConfigured('Kapasitas disk tidak dapat dibaca dari proses aplikasi.');
        }

        $usedPercent = round((1 - ($free / $total)) * 100, 1);
        $metadata = ['used_percent' => $usedPercent, 'free_gb' => round($free / 1073741824, 1), 'total_gb' => round($total / 1073741824, 1), 'log_size_mb' => $this->logSizeMb()];
        $warning = (float) config('monitoring.checks.disk_space.warning_percent', 75);
        $critical = (float) config('monitoring.checks.disk_space.critical_percent', 90);

        return match (true) {
            $usedPercent >= $critical => new HealthCheckResult(MonitoringServiceStatus::Down, "Disk terpakai {$usedPercent}% (kritis).", metadata: $metadata),
            $usedPercent >= $warning => new HealthCheckResult(MonitoringServiceStatus::Degraded, "Disk terpakai {$usedPercent}% (peringatan).", metadata: $metadata),
            default => HealthCheckResult::healthy("Disk terpakai {$usedPercent}%.", $metadata),
        };
    }

    private function logSizeMb(): ?float
    {
        $files = glob(storage_path('logs/*.log')) ?: [];

        return round(array_sum(array_map(fn (string $file): int => (int) @filesize($file), $files)) / 1048576, 1);
    }
}
