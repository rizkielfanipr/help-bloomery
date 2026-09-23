<?php

namespace App\Services;

class EsbGoodsReceiptService
{
    public const COMPANY_CODE = 'BLSS';

    public const PURCHASE_ORDER_STATUS_AUTHORIZED = 3;

    public const PURCHASE_ORDER_STATUS_RECEIVING = 4;

    public function __construct(private readonly EsbCoreClient $client) {}

    /** @return array<int, array<string, mixed>> */
    public function purchaseOrders(array $filters = []): array
    {
        $result = $this->requestResult('get', '/purchase/purchase-order', $filters, 'mengambil Purchase Order');

        return is_array($result['data'] ?? null) ? $result['data'] : (array_is_list($result) ? $result : []);
    }

    /** @return array<string, mixed> */
    public function purchaseOrder(string $purchaseNumber): array
    {
        $path = '/purchase/purchase-order/'.rawurlencode($purchaseNumber);

        return $this->requestResult('get', $path, [], 'mengambil detail Purchase Order');
    }

    /** @return array<int, array<string, mixed>> */
    public function locations(int $branchId): array
    {
        $result = $this->requestResult('get', '/location', ['branchID' => $branchId], 'mengambil lokasi');

        return array_is_list($result) ? $result : [];
    }

    /** @return array<string, mixed> */
    public function create(string $referenceNumber, array $payload): array
    {
        $path = '/inventory/goods-receipt/'.rawurlencode($referenceNumber);
        $response = $this->client->request(self::COMPANY_CODE, 'post', $path, $payload);

        return [
            'result' => $this->client->successfulResult($response, 'membuat Goods Receipt', self::COMPANY_CODE, $path),
            'response' => (array) $response->json(),
        ];
    }

    /** @return array<string, mixed> */
    private function requestResult(string $method, string $path, array $payload, string $action): array
    {
        return $this->client->successfulResult(
            $this->client->request(self::COMPANY_CODE, $method, $path, $payload),
            $action,
            self::COMPANY_CODE,
            $path,
        );
    }
}
