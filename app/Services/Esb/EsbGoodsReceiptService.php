<?php

namespace App\Services\Esb;

use Illuminate\Http\Client\Response;

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
        return app(EsbCoreClient::class)->request(self::COMPANY_CODE, $method, $path, $payload);
    }

    private function successfulResult(Response $response, string $action): array
    {
        return app(EsbCoreClient::class)->successfulResult($response, $action);
    }
}
