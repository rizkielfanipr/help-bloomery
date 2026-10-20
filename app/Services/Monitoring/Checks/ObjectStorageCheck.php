<?php

namespace App\Services\Monitoring\Checks;

use App\Enums\MonitoringServiceStatus;
use App\Models\MonitoringService;
use App\Services\Monitoring\HealthCheckResult;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Controlled probe: writes one tiny file under `monitoring-probe/`, reads it back, and deletes it
 * immediately, so no permanent file is left behind.
 */
class ObjectStorageCheck implements HealthCheck
{
    public function run(MonitoringService $service): HealthCheckResult
    {
        $disk = (string) config('monitoring.checks.object_storage.disk', 'b2');
        $config = (array) config("filesystems.disks.{$disk}", []);
        if ($config === [] || (($config['driver'] ?? null) === 's3' && (blank($config['bucket'] ?? null) || blank($config['key'] ?? null)))) {
            return HealthCheckResult::notConfigured("Disk {$disk} belum dikonfigurasi.");
        }

        $path = 'monitoring-probe/'.Str::uuid().'.txt';
        $contents = 'probe:'.now()->timestamp;

        try {
            $storage = Storage::disk($disk);
            $storage->put($path, $contents);
            $readBack = $storage->get($path);
            $storage->delete($path);
        } catch (Throwable) {
            return new HealthCheckResult(MonitoringServiceStatus::Down, "Probe object storage {$disk} gagal.", metadata: ['disk' => $disk]);
        }

        return $readBack === $contents
            ? HealthCheckResult::healthy("Object storage {$disk} dapat ditulis, dibaca, dan dibersihkan.", ['disk' => $disk])
            : new HealthCheckResult(MonitoringServiceStatus::Degraded, 'Isi probe object storage tidak sesuai.', metadata: ['disk' => $disk]);
    }
}
