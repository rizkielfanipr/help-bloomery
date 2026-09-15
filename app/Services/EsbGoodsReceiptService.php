<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EsbGoodsReceiptService
{
    public const COMPANY_CODE = 'BLSS';

    public const PURCHASE_ORDER_STATUS_AUTHORIZED = 3;

    public const PURCHASE_ORDER_STATUS_RECEIVING = 4;

    /** @return array<int, array<string, mixed>> */
    public function purchaseOrders(array $filters = []): array
    {
        $result = $this->successfulResult($this->request('get', '/purchase/purchase-order', $filters), 'mengambil Purchase Order');

        return is_array($result['data'] ?? null) ? $result['data'] : (array_is_list($result) ? $result : []);
    }

    /** @return array<string, mixed> */
    public function purchaseOrder(string $purchaseNumber): array
    {
        return $this->successfulResult($this->request('get', '/purchase/purchase-order/'.rawurlencode($purchaseNumber)), 'mengambil detail Purchase Order');
    }

    /** @return array<int, array<string, mixed>> */
    public function locations(int $branchId): array
    {
        $result = $this->successfulResult($this->request('get', '/location', ['branchID' => $branchId]), 'mengambil lokasi');

        return array_is_list($result) ? $result : [];
    }

    /** @return array<string, mixed> */
    public function create(string $referenceNumber, array $payload): array
    {
        $response = $this->request('post', '/inventory/goods-receipt/'.rawurlencode($referenceNumber), $payload);

        return ['result' => $this->successfulResult($response, 'membuat Goods Receipt'), 'response' => (array) $response->json()];
    }

    private function request(string $method, string $path, array $payload = []): Response
    {
        $response = $this->send($method, $path, $payload, $this->accessToken());
        if ($response->status() === 401) {
            Cache::forget($this->tokenCacheKey());
            $response = $this->send($method, $path, $payload, $this->accessToken());
        }

        return $response;
    }

    private function send(string $method, string $path, array $payload, string $token): Response
    {
        $request = Http::acceptJson()->asJson()->withToken($token)->connectTimeout(10)->timeout((int) config('esb.core.timeout', 60));

        return $method === 'get' ? $request->get($this->baseUrl().$path, $payload) : $request->post($this->baseUrl().$path, $payload);
    }

    private function accessToken(): string
    {
        $cached = Cache::get($this->tokenCacheKey());
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return Cache::lock('esb_core.login_lock.'.self::COMPANY_CODE, 15)->block(10, function (): string {
            $cached = Cache::get($this->tokenCacheKey());
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
            $username = (string) config('esb.core.companies.'.self::COMPANY_CODE.'.username');
            $password = (string) config('esb.core.companies.'.self::COMPANY_CODE.'.password');
            if ($username === '' || $password === '') {
                throw new RuntimeException('Credential ESB Core BLSS belum dikonfigurasi.');
            }
            $response = Http::acceptJson()->asJson()->connectTimeout(10)->timeout((int) config('esb.core.timeout', 60))
                ->post($this->baseUrl().'/auth/login', compact('username', 'password'));
            $token = (string) data_get($response->json(), 'result.accessToken', '');
            if ($response->failed() || $token === '') {
                throw new RuntimeException($this->errorMessage($response, 'login ke ESB Core BLSS'));
            }
            Cache::put($this->tokenCacheKey(), $token, max(60, (int) config('esb.core.token_ttl', 3300)));

            return $token;
        });
    }

    /** @return array<string, mixed> */
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

    private function tokenCacheKey(): string
    {
        return 'esb_core.access_token.'.self::COMPANY_CODE;
    }
}
