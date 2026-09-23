<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EsbCoreClient
{
    public function request(string $companyCode, string $method, string $path, array $data = []): Response
    {
        $companyCode = $this->normalizeCompanyCode($companyCode);

        try {
            $response = $this->send($companyCode, $method, $path, $data);
            if ($response->status() === 401) {
                $this->forgetToken($companyCode);
                $response = $this->send($companyCode, $method, $path, $data);
            }

            return $response;
        } catch (ConnectionException $exception) {
            throw new RuntimeException("Gagal menghubungi ESB Core [{$companyCode} {$path}]: {$exception->getMessage()}", previous: $exception);
        }
    }

    /**
     * @param  array<int, array{name:string,contents:string,mime:?string}>  $files
     */
    public function multipart(string $companyCode, string $method, string $path, array $files, string $field = 'files'): Response
    {
        $companyCode = $this->normalizeCompanyCode($companyCode);

        try {
            $response = $this->sendMultipart($companyCode, $method, $path, $files, $field);
            if ($response->status() === 401) {
                $this->forgetToken($companyCode);
                $response = $this->sendMultipart($companyCode, $method, $path, $files, $field);
            }

            return $response;
        } catch (ConnectionException $exception) {
            throw new RuntimeException("Gagal menghubungi ESB Core [{$companyCode} {$path}]: {$exception->getMessage()}", previous: $exception);
        }
    }

    /** @return array<string, mixed> */
    public function successfulResult(Response $response, string $action, string $companyCode, string $path): array
    {
        $payload = $response->json();
        if ($response->failed() || ! is_array($payload) || ($payload['status'] ?? null) !== 'ok') {
            throw new RuntimeException($this->errorMessage($response, $action, $companyCode, $path));
        }

        return is_array($payload['result'] ?? null) ? $payload['result'] : [];
    }

    public function forgetToken(string $companyCode): void
    {
        Cache::forget($this->tokenCacheKey($this->normalizeCompanyCode($companyCode)));
    }

    private function send(string $companyCode, string $method, string $path, array $data): Response
    {
        $request = $this->authenticatedRequest($companyCode);
        $url = $this->url($path);

        return match (mb_strtolower($method)) {
            'get' => $request->get($url, $data),
            'delete' => $request->delete($url, $data),
            'patch' => $request->patch($url, $data),
            'put' => $request->put($url, $data),
            default => $request->post($url, $data),
        };
    }

    /** @param array<int, array{name:string,contents:string,mime:?string}> $files */
    private function sendMultipart(string $companyCode, string $method, string $path, array $files, string $field): Response
    {
        $request = $this->authenticatedRequest($companyCode);
        foreach ($files as $file) {
            $request = $request->attach($field, $file['contents'], $file['name'], array_filter(['Content-Type' => $file['mime']]));
        }

        return match (mb_strtolower($method)) {
            'post' => $request->post($this->url($path)),
            'put' => $request->put($this->url($path)),
            default => $request->patch($this->url($path)),
        };
    }

    private function authenticatedRequest(string $companyCode): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->withToken($this->accessToken($companyCode))
            ->connectTimeout((int) config('esb.core.connect_timeout', 10))
            ->timeout((int) config('esb.core.timeout', 60));
    }

    private function accessToken(string $companyCode): string
    {
        $key = $this->tokenCacheKey($companyCode);
        $cached = Cache::get($key);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return Cache::lock("esb_core.login_lock.{$companyCode}", 15)->block(10, function () use ($companyCode, $key): string {
            $cached = Cache::get($key);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }

            $credentials = (array) config("esb.core.companies.{$companyCode}", []);
            $username = trim((string) ($credentials['username'] ?? ''));
            $password = (string) ($credentials['password'] ?? '');
            if ($username === '' || $password === '') {
                throw new RuntimeException("Credential ESB Core {$companyCode} belum dikonfigurasi.");
            }

            try {
                $response = Http::acceptJson()
                    ->asJson()
                    ->connectTimeout((int) config('esb.core.connect_timeout', 10))
                    ->timeout((int) config('esb.core.timeout', 60))
                    ->post($this->url('/auth/login'), compact('username', 'password'));
            } catch (ConnectionException $exception) {
                throw new RuntimeException("Gagal menghubungi ESB Core [{$companyCode} /auth/login]: {$exception->getMessage()}", previous: $exception);
            }

            $payload = $response->json();
            $token = is_array($payload) ? (string) data_get($payload, 'result.accessToken', '') : '';
            if ($response->failed() || $token === '') {
                throw new RuntimeException($this->errorMessage($response, "login ke ESB Core {$companyCode}", $companyCode, '/auth/login'));
            }

            Cache::put($key, $token, max(60, (int) config('esb.core.token_ttl', 3300)));

            return $token;
        });
    }

    private function errorMessage(Response $response, string $action, string $companyCode, string $path): string
    {
        $payload = $response->json();
        $messages = collect(is_array($payload) ? ($payload['errors'] ?? []) : [])->pluck('message')->filter();
        $detail = $messages->isNotEmpty() ? $messages->implode('; ') : data_get($payload, 'message');

        return 'Gagal '.$action." [{$companyCode} {$path}]".($detail ? ': '.$detail : ' (HTTP '.$response->status().').');
    }

    private function url(string $path): string
    {
        return rtrim((string) config('esb.core.base_url'), '/').'/'.ltrim($path, '/');
    }

    private function tokenCacheKey(string $companyCode): string
    {
        return 'esb_core.access_token.'.$companyCode;
    }

    private function normalizeCompanyCode(string $companyCode): string
    {
        $normalized = mb_strtoupper(trim($companyCode));
        if ($normalized === '') {
            throw new RuntimeException('Company Code ESB wajib diisi.');
        }

        return $normalized;
    }
}
