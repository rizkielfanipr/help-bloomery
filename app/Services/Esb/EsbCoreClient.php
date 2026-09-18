<?php

namespace App\Services\Esb;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EsbCoreClient
{
    public function request(?string $company, string $method, string $path, array $data = [], bool $refreshToken = true): Response
    {
        $response = $this->authenticatedRequest($company)->send(strtoupper($method), $this->baseUrl().$path, [
            $method === 'get' ? 'query' : 'json' => $data,
        ]);
        if ($response->status() === 401 && $refreshToken) {
            Cache::forget($this->tokenCacheKey($company));

            return $this->request($company, $method, $path, $data, false);
        }

        return $response;
    }

    /** @param list<array{name:string,contents:string,mime:?string}> $files */
    public function upload(?string $company, string $path, array $files, bool $refreshToken = true): Response
    {
        $request = $this->authenticatedRequest($company);
        foreach ($files as $file) {
            $request->attach('files', $file['contents'], $file['name'], array_filter(['Content-Type' => $file['mime']]));
        }
        $response = $request->patch($this->baseUrl().$path);
        if ($response->status() === 401 && $refreshToken) {
            Cache::forget($this->tokenCacheKey($company));

            return $this->upload($company, $path, $files, false);
        }

        return $response;
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

    private function authenticatedRequest(?string $company): PendingRequest
    {
        return $this->httpRequest()->withToken($this->accessToken($company));
    }

    private function httpRequest(): PendingRequest
    {
        return Http::acceptJson()->asJson()->connectTimeout(10)->timeout((int) config('esb.core.timeout', 60));
    }

    private function accessToken(?string $company): string
    {
        $key = $this->tokenCacheKey($company);
        if (is_string($token = Cache::get($key)) && $token !== '') {
            return $token;
        }

        return Cache::lock('esb_core.login_lock'.($company === null ? '' : '.'.$company), 15)->block(10, function () use ($company, $key): string {
            if (is_string($token = Cache::get($key)) && $token !== '') {
                return $token;
            }
            $credentials = $company === null
                ? ['username' => config('esb.core.username'), 'password' => config('esb.core.password')]
                : (array) config("esb.core.companies.{$company}", []);
            if ($this->baseUrl() === '' || blank($credentials['username'] ?? null) || blank($credentials['password'] ?? null)) {
                throw new RuntimeException($company === null ? 'Konfigurasi koneksi ESB Core belum lengkap.' : "Credential ESB Core {$company} belum dikonfigurasi.");
            }
            $response = $this->httpRequest()->post($this->baseUrl().'/auth/login', [
                'username' => $credentials['username'], 'password' => $credentials['password'],
            ]);
            $result = $this->successfulResult($response, 'login ke ESB Core'.($company === null ? '' : ' '.$company));
            $token = $result['accessToken'] ?? null;
            if (! is_string($token) || $token === '') {
                throw new RuntimeException('ESB Core tidak mengembalikan access token.');
            }
            Cache::put($key, $token, max(60, (int) config('esb.core.token_ttl', 3300)));

            return $token;
        });
    }

    private function errorMessage(Response $response, string $action): string
    {
        $payload = $response->json();
        $errors = is_array($payload) && is_array($payload['errors'] ?? null) ? $payload['errors'] : [];
        $messages = collect($errors)->pluck('message')->filter(fn ($message): bool => is_string($message) && $message !== '');
        $message = is_array($payload) && is_string($payload['message'] ?? null) ? $payload['message'] : null;
        $detail = $messages->isNotEmpty() ? $messages->implode('; ') : $message;

        return 'Gagal '.$action.($detail ? ': '.$detail : ' (HTTP '.$response->status().').');
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('esb.core.base_url'), '/');
    }

    private function tokenCacheKey(?string $company): string
    {
        return 'esb_core.access_token'.($company === null ? '' : '.'.$company);
    }
}
