<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EsbItemJournalService
{
    /** @return array<int, array<string, mixed>> */
    public function branches(string $companyCode): array
    {
        return Cache::remember("esb_item_journal.branches.{$companyCode}", now()->addMinutes(30), function () use ($companyCode): array {
            $result = $this->successfulResult($this->request($companyCode, 'get', '/branch'), 'mengambil daftar cabang');

            return array_is_list($result) ? $result : [];
        });
    }

    /** @return array<string, mixed> */
    public function branchByCode(string $companyCode, string $branchCode): array
    {
        $branch = collect($this->branches($companyCode))->first(
            fn (array $branch): bool => strcasecmp(trim((string) ($branch['branchCode'] ?? '')), trim($branchCode)) === 0
        );

        if (! is_array($branch)) {
            throw new RuntimeException("ESB Branch Code {$branchCode} tidak ditemukan pada company {$companyCode}.");
        }

        return $branch;
    }

    /** @return array<int, array<string, mixed>> */
    public function locations(string $companyCode, int $branchId): array
    {
        $result = $this->successfulResult($this->request($companyCode, 'get', '/location', ['branchID' => $branchId]), 'mengambil daftar lokasi');

        return array_is_list($result) ? $result : [];
    }

    /** @return array<int, array<string, mixed>> */
    public function purposes(string $companyCode): array
    {
        return Cache::remember("esb_item_journal.purposes.{$companyCode}", now()->addMinutes(30), function () use ($companyCode): array {
            $purposes = [];
            $page = 1;

            do {
                $result = $this->successfulResult($this->request($companyCode, 'get', '/purpose', [
                    'page' => $page,
                    'limit' => 100,
                    'flagActive' => 1,
                    'sort' => 'purposeName',
                ]), 'mengambil daftar purpose');
                array_push($purposes, ...(is_array($result['data'] ?? null) ? $result['data'] : []));
                $page++;
            } while (filled($result['next'] ?? null) && $page <= 100);

            return collect($purposes)->filter(function (array $purpose): bool {
                $appliedTo = collect($purpose['purposeAppliedTo'] ?? [])->map(fn ($value): string => strtoupper((string) $value));

                return (bool) ($purpose['flagActive'] ?? false)
                    && ($appliedTo->isEmpty() || $appliedTo->contains(fn (string $value): bool => str_contains($value, 'ITEM JOURNAL')));
            })->values()->all();
        });
    }

    /** @return array{page:int,limit:int,count:int,data:array<int, mixed>,prev:?string,next:?string} */
    public function products(string $companyCode, array $filters = []): array
    {
        $result = $this->successfulResult($this->request($companyCode, 'get', '/product/list', array_filter([
            'page' => max(1, (int) ($filters['page'] ?? 1)),
            'limit' => min(100, max(1, (int) ($filters['limit'] ?? 20))),
            'productName' => $filters['productName'] ?? null,
            'productCode' => $filters['productCode'] ?? null,
            'flagActive' => 1,
        ], fn ($value) => $value !== null && $value !== '')), 'mengambil daftar produk');

        return [
            'page' => (int) ($result['page'] ?? 1),
            'limit' => (int) ($result['limit'] ?? 20),
            'count' => (int) ($result['count'] ?? 0),
            'data' => is_array($result['data'] ?? null) ? $result['data'] : [],
            'prev' => filled($result['prev'] ?? null) ? (string) $result['prev'] : null,
            'next' => filled($result['next'] ?? null) ? (string) $result['next'] : null,
        ];
    }

    /** @return array{itemJournalNum:string,response:array<string,mixed>} */
    public function create(string $companyCode, array $payload): array
    {
        $response = $this->request($companyCode, 'post', '/inventory/item-journal', $payload);
        $result = $this->successfulResult($response, 'membuat Item Journal');
        $number = (string) ($result['itemJournalNum'] ?? '');
        if ($number === '') {
            throw new RuntimeException('ESB tidak mengembalikan nomor Item Journal.');
        }

        return ['itemJournalNum' => $number, 'response' => (array) $response->json()];
    }

    /** @param array<int, array{name:string,contents:string,mime:?string}> $files */
    public function uploadAttachments(string $companyCode, string $journalNumber, array $files): array
    {
        $request = $this->authenticatedRequest($companyCode);
        foreach ($files as $file) {
            $request = $request->attach('files', $file['contents'], $file['name'], array_filter(['Content-Type' => $file['mime']]));
        }
        $response = $request->patch($this->baseUrl().'/inventory/item-journal/'.rawurlencode($journalNumber).'/attachment');
        if ($response->status() === 401) {
            Cache::forget($this->tokenCacheKey($companyCode));

            return $this->uploadAttachments($companyCode, $journalNumber, $files);
        }

        return $this->successfulResult($response, 'mengunggah attachment Item Journal')['urls'] ?? [];
    }

    public function deleteAttachments(string $companyCode, string $journalNumber): void
    {
        $this->successfulResult($this->request($companyCode, 'delete', '/inventory/item-journal/'.rawurlencode($journalNumber).'/attachment'), 'menghapus attachment Item Journal');
    }

    private function request(string $companyCode, string $method, string $path, array $data = []): Response
    {
        $response = match ($method) {
            'get' => $this->authenticatedRequest($companyCode)->get($this->baseUrl().$path, $data),
            'delete' => $this->authenticatedRequest($companyCode)->delete($this->baseUrl().$path, $data),
            default => $this->authenticatedRequest($companyCode)->post($this->baseUrl().$path, $data),
        };
        if ($response->status() === 401) {
            Cache::forget($this->tokenCacheKey($companyCode));

            return $this->request($companyCode, $method, $path, $data);
        }

        return $response;
    }

    private function authenticatedRequest(string $companyCode): PendingRequest
    {
        return Http::acceptJson()->asJson()->withToken($this->accessToken($companyCode))->connectTimeout(10)->timeout((int) config('esb.core.timeout', 60));
    }

    private function accessToken(string $companyCode): string
    {
        $key = $this->tokenCacheKey($companyCode);
        if (is_string($token = Cache::get($key)) && $token !== '') {
            return $token;
        }
        $credentials = (array) config("esb.core.companies.{$companyCode}", []);
        if (blank($credentials['username'] ?? null) || blank($credentials['password'] ?? null)) {
            throw new RuntimeException("Credential ESB Core {$companyCode} belum dikonfigurasi.");
        }
        $response = Http::acceptJson()->asJson()->post($this->baseUrl().'/auth/login', $credentials);
        $token = (string) data_get($response->json(), 'result.accessToken', '');
        if ($response->failed() || $token === '') {
            throw new RuntimeException($this->errorMessage($response, "login ke ESB Core {$companyCode}"));
        }
        Cache::put($key, $token, max(60, (int) config('esb.core.token_ttl', 3300)));

        return $token;
    }

    private function successfulResult(Response $response, string $action): array
    {
        $payload = $response->json();
        if ($response->failed() || ! is_array($payload) || ($payload['status'] ?? null) !== 'ok') {
            throw new RuntimeException($this->errorMessage($response, $action));
        }

        return is_array($payload['result'] ?? null) ? $payload['result'] : [];
    }

    private function errorMessage(Response $response, string $action): string
    {
        $payload = $response->json();
        $messages = collect(is_array($payload) ? ($payload['errors'] ?? []) : [])->pluck('message')->filter();
        $detail = $messages->isNotEmpty() ? $messages->implode('; ') : data_get($payload, 'message');

        return 'Gagal '.$action.($detail ? ': '.$detail : ' (HTTP '.$response->status().').');
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('esb.core.base_url'), '/');
    }

    private function tokenCacheKey(string $companyCode): string
    {
        return 'esb_core.access_token.'.$companyCode;
    }
}
