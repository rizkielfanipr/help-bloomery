<?php

namespace App\Services\Monitoring\Checks;

use App\Enums\MonitoringServiceStatus;
use App\Models\MonitoringService;
use App\Services\Monitoring\HealthCheckResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Requests the framework's unauthenticated `/up` health route through the public URL, which also
 * exercises the web server and TLS path. It exposes no sensitive data.
 */
class ApplicationCheck implements HealthCheck
{
    public function run(MonitoringService $service): HealthCheckResult
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        if ($baseUrl === '' || str_contains($baseUrl, 'localhost')) {
            return HealthCheckResult::notConfigured('APP_URL belum dikonfigurasi untuk pemeriksaan aplikasi.');
        }

        try {
            $response = Http::timeout($service->timeout_seconds)->connectTimeout($service->timeout_seconds)->get($baseUrl.'/up');
        } catch (ConnectionException) {
            return new HealthCheckResult(MonitoringServiceStatus::Down, 'Aplikasi tidak dapat dihubungi dari scheduler.');
        }

        if ($response->successful()) {
            return new HealthCheckResult(MonitoringServiceStatus::Healthy, 'Health route merespons normal.', httpStatus: $response->status());
        }

        return new HealthCheckResult(
            $response->serverError() ? MonitoringServiceStatus::Down : MonitoringServiceStatus::Degraded,
            'Health route merespons HTTP '.$response->status().'.',
            httpStatus: $response->status(),
        );
    }
}
