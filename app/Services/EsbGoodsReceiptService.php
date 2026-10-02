<?php

namespace App\Services;

/**
 * docs/receiving-simplification-prd.md §12: every operation now takes the Company Code as an
 * explicit, caller-supplied context (resolved by the caller from the selected PO's own mapping —
 * see EsbBranchMappingResolver) rather than a single constant, so Receiving is no longer limited
 * to BLSS.
 */
class EsbGoodsReceiptService
{
    public const PURCHASE_ORDER_STATUS_AUTHORIZED = 3;

    public const PURCHASE_ORDER_STATUS_RECEIVING = 4;

    public function __construct(private readonly EsbCoreClient $client) {}

    /** @return array<int, array<string, mixed>> */
    public function purchaseOrders(string $companyCode, array $filters = []): array
    {
        $result = $this->requestResult($companyCode, 'get', '/purchase/purchase-order', $filters, 'mengambil Purchase Order');

        return is_array($result['data'] ?? null) ? $result['data'] : (array_is_list($result) ? $result : []);
    }

    /** @return array<string, mixed> */
    public function purchaseOrder(string $companyCode, string $purchaseNumber): array
    {
        $path = '/purchase/purchase-order/'.rawurlencode($purchaseNumber);

        return $this->requestResult($companyCode, 'get', $path, [], 'mengambil detail Purchase Order');
    }

    /** @return array<int, array<string, mixed>> */
    public function locations(string $companyCode, int $branchId): array
    {
        $result = $this->requestResult($companyCode, 'get', '/location', ['branchID' => $branchId], 'mengambil lokasi');

        return array_is_list($result) ? $result : [];
    }

    /** @return array<string, mixed> */
    public function create(string $companyCode, string $referenceNumber, array $payload): array
    {
        $path = '/inventory/goods-receipt/'.rawurlencode($referenceNumber);
        $response = $this->client->request($companyCode, 'post', $path, $payload);

        return [
            'result' => $this->client->successfulResult($response, 'membuat Goods Receipt', $companyCode, $path),
            'response' => (array) $response->json(),
        ];
    }

    /** @return array<string, mixed> */
    private function requestResult(string $companyCode, string $method, string $path, array $payload, string $action): array
    {
        return $this->client->successfulResult(
            $this->client->request($companyCode, $method, $path, $payload),
            $action,
            $companyCode,
            $path,
        );
    }
}
