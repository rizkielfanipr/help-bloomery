<?php

namespace App\Services\Monitoring\Checks;

use App\Enums\MonitoringServiceStatus;
use App\Models\MonitoringService;
use App\Services\EsbCoreClient;
use App\Services\EsbGlobalCoreClient;
use App\Services\Monitoring\HealthCheckResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Lightweight, read-only ESB probes (docs/system-monitoring-prd.md §10.1). Every probe is a
 * single GET with `limit=1` or a small reference list. The existing clients reuse their cached
 * access token, so a probe only logs in when the token expired. ESB timeouts are lowered to the
 * service timeout for the duration of the probe.
 */
class EsbReadCheck implements HealthCheck
{
    public function run(MonitoringService $service): HealthCheckResult
    {
        $originalTimeouts = [config('esb.core.timeout'), config('esb.core.connect_timeout')];
        config(['esb.core.timeout' => $service->timeout_seconds, 'esb.core.connect_timeout' => min(5, $service->timeout_seconds)]);

        try {
            return match ($service->key) {
                'esb_core' => $this->globalCore('/product/list'),
                'esb_bom' => $this->globalCore('/product/bom'),
                'esb_company_core' => $this->companyCore((string) config('monitoring.checks.esb_company_core.company_code', 'BLSS')),
                'esb_promotion' => $this->promotion((string) config('monitoring.checks.esb_promotion.company_code', 'BLSS'), $service->timeout_seconds),
                default => HealthCheckResult::notConfigured('Pemeriksaan ESB tidak dikenal.'),
            };
        } finally {
            config(['esb.core.timeout' => $originalTimeouts[0], 'esb.core.connect_timeout' => $originalTimeouts[1]]);
        }
    }

    private function globalCore(string $path): HealthCheckResult
    {
        if (blank(config('esb.core.base_url')) || blank(config('esb.core.username')) || blank(config('esb.core.password'))) {
            return HealthCheckResult::notConfigured('Kredensial ESB Core global belum dikonfigurasi.');
        }

        return $this->evaluate(fn (): Response => app(EsbGlobalCoreClient::class)->request('get', $path, ['page' => 1, 'limit' => 1]));
    }

    private function companyCore(string $companyCode): HealthCheckResult
    {
        $credentials = (array) config("esb.core.companies.{$companyCode}", []);
        if (blank($credentials['username'] ?? null) || blank($credentials['password'] ?? null)) {
            return HealthCheckResult::notConfigured("Kredensial ESB Core {$companyCode} belum dikonfigurasi.");
        }

        return $this->evaluate(fn (): Response => app(EsbCoreClient::class)->request($companyCode, 'get', '/branch'), ['company_code' => $companyCode]);
    }

    private function promotion(string $companyCode, int $timeout): HealthCheckResult
    {
        $token = trim((string) config("esb.tokens.{$companyCode}", ''));
        if ($token === '' || blank(config('esb.base_url'))) {
            return HealthCheckResult::notConfigured("Token ESB {$companyCode} belum dikonfigurasi.");
        }

        return $this->evaluate(fn (): Response => Http::acceptJson()->withToken($token)->connectTimeout(min(5, $timeout))->timeout($timeout)
            ->get(rtrim((string) config('esb.base_url'), '/').'/corev1/branch'), ['company_code' => $companyCode]);
    }

    /**
     * @param  callable(): Response  $probe
     * @param  array<string, mixed>  $metadata
     */
    private function evaluate(callable $probe, array $metadata = []): HealthCheckResult
    {
        try {
            $response = $probe();
        } catch (ConnectionException) {
            return new HealthCheckResult(MonitoringServiceStatus::Down, 'ESB tidak dapat dihubungi (timeout atau koneksi gagal).', metadata: $metadata);
        } catch (RuntimeException $exception) {
            return new HealthCheckResult(
                MonitoringServiceStatus::Down,
                $exception->getPrevious() instanceof ConnectionException ? 'ESB tidak dapat dihubungi (timeout atau koneksi gagal).' : 'Autentikasi ESB gagal.',
                metadata: $metadata,
            );
        } catch (Throwable) {
            return new HealthCheckResult(MonitoringServiceStatus::Down, 'Pemeriksaan ESB gagal.', metadata: $metadata);
        }

        $payload = $response->json();
        $declaredOk = is_array($payload) && (($payload['status'] ?? null) === 'ok' || (int) ($payload['code'] ?? 0) === 200);

        return match (true) {
            $response->successful() && $declaredOk => new HealthCheckResult(MonitoringServiceStatus::Healthy, 'ESB merespons normal.', httpStatus: $response->status(), metadata: $metadata),
            in_array($response->status(), [401, 403], true) => new HealthCheckResult(MonitoringServiceStatus::Down, 'ESB menolak autentikasi.', httpStatus: $response->status(), metadata: $metadata),
            $response->serverError() => new HealthCheckResult(MonitoringServiceStatus::Down, 'ESB mengembalikan error server.', httpStatus: $response->status(), metadata: $metadata),
            default => new HealthCheckResult(MonitoringServiceStatus::Degraded, 'Respons ESB tidak sesuai format yang diharapkan.', httpStatus: $response->status(), metadata: $metadata),
        };
    }
}
