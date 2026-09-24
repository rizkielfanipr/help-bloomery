<?php

namespace App\Services;

class EsbPurchaseOrderService
{
    public function __construct(private readonly EsbGlobalCoreClient $client) {}

    /**
     * @return array{page:int, limit:int, count:int, data:array<int, mixed>, prev:?string, next:?string}
     */
    public function getPurchaseOrders(array $filters = []): array
    {
        $response = $this->client->request('get', '/purchase/purchase-order', array_filter([
            'page' => max(1, (int) ($filters['page'] ?? 1)),
            'limit' => min(100, max(1, (int) ($filters['limit'] ?? 100))),
            'sort' => $filters['sort'] ?? '-purchaseDate',
            'purchaseNum' => $filters['purchaseNum'] ?? null,
            'branchID' => $filters['branchID'] ?? null,
            'supplierID' => $filters['supplierID'] ?? null,
            'statusID' => $filters['statusID'] ?? null,
            'dateFrom' => $filters['dateFrom'] ?? null,
            'dateTo' => $filters['dateTo'] ?? null,
        ], fn ($value) => $value !== null && $value !== ''));

        $result = $this->client->successfulResult($response, 'mengambil daftar Purchase Order');

        return [
            'page' => (int) ($result['page'] ?? 1),
            'limit' => (int) ($result['limit'] ?? 100),
            'count' => (int) ($result['count'] ?? 0),
            'data' => is_array($result['data'] ?? null) ? $result['data'] : [],
            'prev' => ($result['prev'] ?? null) ?: null,
            'next' => ($result['next'] ?? null) ?: null,
        ];
    }

    /** @return array<string, mixed> */
    public function getPurchaseOrder(string $purchaseNum): array
    {
        $path = '/purchase/purchase-order/'.rawurlencode($purchaseNum);

        return $this->client->successfulResult(
            $this->client->request('get', $path),
            'mengambil detail Purchase Order '.$purchaseNum,
        );
    }
}
