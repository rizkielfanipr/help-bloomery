<?php

namespace App\Services\Monitoring\Checks;

use App\Enums\MonitoringServiceStatus;
use App\Models\MonitoringService;
use App\Services\Monitoring\HealthCheckResult;
use Carbon\CarbonImmutable;

/**
 * Reads the public certificate expiry of APP_URL. Disabled by default; enable only when the
 * scheduler host can reach the public hostname.
 */
class SslCertificateCheck implements HealthCheck
{
    public function run(MonitoringService $service): HealthCheckResult
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (! is_string($host) || $host === '' || parse_url((string) config('app.url'), PHP_URL_SCHEME) !== 'https') {
            return HealthCheckResult::notConfigured('APP_URL HTTPS belum dikonfigurasi.');
        }

        $context = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'SNI_enabled' => true, 'peer_name' => $host]]);
        $client = @stream_socket_client("ssl://{$host}:443", $errorCode, $errorMessage, $service->timeout_seconds, STREAM_CLIENT_CONNECT, $context);
        if (! $client) {
            return new HealthCheckResult(MonitoringServiceStatus::Down, 'Handshake TLS gagal.');
        }

        $certificate = stream_context_get_params($client)['options']['ssl']['peer_certificate'] ?? null;
        fclose($client);
        $parsed = $certificate ? openssl_x509_parse($certificate) : false;
        if (! is_array($parsed) || ! isset($parsed['validTo_time_t'])) {
            return new HealthCheckResult(MonitoringServiceStatus::Unknown, 'Sertifikat tidak dapat dibaca.');
        }

        $expiresAt = CarbonImmutable::createFromTimestamp($parsed['validTo_time_t']);
        $days = (int) floor(now()->diffInDays($expiresAt, false));
        $metadata = ['expires_at' => $expiresAt->toIso8601String(), 'days_remaining' => $days];

        return match (true) {
            $days <= (int) config('monitoring.checks.ssl_certificate.critical_days', 14) => new HealthCheckResult(MonitoringServiceStatus::Down, "Sertifikat kedaluwarsa dalam {$days} hari.", metadata: $metadata),
            $days <= (int) config('monitoring.checks.ssl_certificate.warning_days', 30) => new HealthCheckResult(MonitoringServiceStatus::Degraded, "Sertifikat kedaluwarsa dalam {$days} hari.", metadata: $metadata),
            default => HealthCheckResult::healthy("Sertifikat berlaku {$days} hari lagi.", $metadata),
        };
    }
}
