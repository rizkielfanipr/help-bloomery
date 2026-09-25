<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EsbGlobalCoreClient
{
    private const TOKEN_CACHE_KEY = 'esb_core.access_token';

    public function request(string $method, string $path, array $data = []): Response
    {
        $response = $this->send($method, $path, $data, $this->accessToken());

        if ($response->status() === 401) {
            Cache::forget(self::TOKEN_CACHE_KEY);
            $response = $this->send($method, $path, $data, $this->accessToken());
        }

        return $response;
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $queries
     * @return array<int|string, Response>
     */
    public function poolGet(string $path, array $queries): array
    {
        $token = $this->accessToken();
        $url = rtrim((string) config('esb.core.base_url'), '/').'/'.ltrim($path, '/');

        return Http::pool(fn ($pool): array => collect($queries)
            ->map(fn (array $query, int|string $key) => $pool
                ->as((string) $key)
                ->withToken($token)
                ->acceptJson()
                ->timeout((int) config('esb.core.timeout', 60))
                ->get($url, $query))
            ->all());
    }

    /** @param array<int|string, string> $paths */
    public function poolGetPaths(array $paths): array
    {
        $token = $this->accessToken();
        $baseUrl = rtrim((string) config('esb.core.base_url'), '/');

        return Http::pool(fn ($pool): array => collect($paths)
            ->map(fn (string $path, int|string $key) => $pool
                ->as((string) $key)
                ->withToken($token)
                ->acceptJson()
                ->timeout((int) config('esb.core.timeout', 60))
                ->get($baseUrl.'/'.ltrim($path, '/')))
            ->all());
    }

    /** @return array<string, mixed> */
    public function successfulResult(Response $response, string $action): array
    {
        $payload = $response->json();

        if ($response->failed() || ! is_array($payload) || ($payload['status'] ?? null) !== 'ok') {
            throw new RuntimeException($this->errorMessage($response, $action));
        }

        return is_array($payload['result'] ?? null) ? $payload['result'] : [];
    }

    private function send(string $method, string $path, array $data, string $token): Response
    {
        $request = Http::acceptJson()
            ->asJson()
            ->withToken($token)
            ->timeout((int) config('esb.core.timeout', 60));

        $url = rtrim((string) config('esb.core.base_url'), '/').'/'.ltrim($path, '/');

        return match (mb_strtolower($method)) {
            'get' => $request->get($url, $data),
            'put' => $request->put($url, $data),
            default => $request->post($url, $data),
        };
    }

    private function accessToken(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return Cache::lock('esb_core.login_lock', 15)->block(10, function (): string {
            $cached = Cache::get(self::TOKEN_CACHE_KEY);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }

            $baseUrl = rtrim((string) config('esb.core.base_url'), '/');
            $username = (string) config('esb.core.username');
            $password = (string) config('esb.core.password');

            if ($baseUrl === '' || $username === '' || $password === '') {
                throw new RuntimeException('Konfigurasi koneksi ESB Core belum lengkap.');
            }

            $response = Http::acceptJson()
                ->asJson()
                ->timeout((int) config('esb.core.timeout', 60))
                ->post($baseUrl.'/auth/login', compact('username', 'password'));

            $payload = $response->json();
            $token = is_array($payload) ? (string) data_get($payload, 'result.accessToken', '') : '';

            if ($response->failed() || $token === '') {
                throw new RuntimeException($this->errorMessage($response, 'login ke ESB Core'));
            }

            Cache::put(self::TOKEN_CACHE_KEY, $token, max(60, (int) config('esb.core.token_ttl', 3300)));

            return $token;
        });
    }

    private function errorMessage(Response $response, string $action): string
    {
        $payload = $response->json();
        $messages = collect(is_array($payload) ? ($payload['errors'] ?? []) : [])
            ->pluck('message')
            ->filter()
            ->values();
        $message = is_array($payload) ? ($payload['message'] ?? null) : null;
        $detail = $messages->isNotEmpty() ? $messages->implode('; ') : $message;

        return 'Gagal '.$action.($detail ? ': '.$detail : ' (HTTP '.$response->status().').');
    }
}
