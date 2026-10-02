<?php

namespace App\Services;

use RuntimeException;

/**
 * ESB Core Product Sales lookup for Store Sales Order (docs/store-sales-order-prd.md §9).
 * Read-only: no create/update/cancel/delete method exists or will be added here — this feature
 * never mutates ESB (§5.2, Quality gate #7).
 *
 * Response field names (`productSalesNum`, `branchID`, etc.) follow the PRD's documented contract
 * exactly, since the Phase 0 audit could not reach a real response (the configured ESB Core
 * credentials returned 403 "no access" for this endpoint — an account-scope gap on ESB's side,
 * not a wrong path). Every field is read defensively (trim/filled/is_numeric guards, never direct
 * array access) so a real response with slightly different field names degrades to nulls instead
 * of a fatal error, and a later contract verification pass only needs to adjust `normalize()`.
 */
class EsbProductSalesService
{
    private const LOOKUP_LIMIT = 10;

    public function __construct(private readonly EsbCoreClient $client) {}

    /**
     * Exact lookup only: the PRD explicitly forbids trusting the first row ESB returns ("Jangan
     * menganggap hasil pertama selalu benar") — every row in the page is re-checked against both
     * the requested number and branch before being accepted. Returns null when no row matches
     * (ESB "no data" and "found other rows but none matching" both end up here identically).
     *
     * @return array<string, mixed>|null
     */
    public function exactLookup(string $companyCode, int $esbBranchId, string $productSalesNumber): ?array
    {
        $number = trim($productSalesNumber);

        if ($number === '') {
            throw new RuntimeException('Nomor Sales Order wajib diisi.');
        }

        $response = $this->client->request($companyCode, 'get', '/sales/product-sales', [
            'productSalesNum' => $number,
            'branchID' => $esbBranchId,
            'page' => 1,
            'limit' => self::LOOKUP_LIMIT,
        ]);

        $result = $this->client->successfulResult($response, 'mencari Sales Order', $companyCode, '/sales/product-sales');

        foreach ($this->extractRows($result) as $row) {
            if ($this->isExactMatch($row, $esbBranchId, $number)) {
                return $this->normalize($row);
            }
        }

        return null;
    }

    /**
     * ESB's pagination envelope for this endpoint is unverified (§9.3) — this accepts either a
     * `result.data` list (the shape every other confirmed ESB Core/Master endpoint in this app
     * uses) or `result` itself already being the list, so either turns out to be correct without
     * a crash.
     *
     * @param  array<string, mixed>  $result
     * @return list<array<string, mixed>>
     */
    private function extractRows(array $result): array
    {
        if (is_array($result['data'] ?? null)) {
            return array_values(array_filter($result['data'], 'is_array'));
        }

        if (array_is_list($result)) {
            return array_values(array_filter($result, 'is_array'));
        }

        return [];
    }

    /** @param array<string, mixed> $row */
    private function isExactMatch(array $row, int $esbBranchId, string $productSalesNumber): bool
    {
        $rowNumber = trim((string) ($row['productSalesNum'] ?? ''));
        $rowBranchId = (int) ($row['branchID'] ?? 0);

        // Strict, case-sensitive: the PRD asks for "pencocokan exact", not a fuzzy/ci match.
        return $rowNumber !== '' && $rowNumber === $productSalesNumber && $rowBranchId === $esbBranchId;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalize(array $row): array
    {
        return [
            'product_sales_number' => trim((string) ($row['productSalesNum'] ?? '')),
            'product_sales_date' => $this->nullableString($row['productSalesDate'] ?? null),
            'required_date' => $this->nullableString($row['requiredDate'] ?? null),
            'esb_branch_id' => (int) ($row['branchID'] ?? 0),
            'branch_name' => $this->nullableString($row['branchName'] ?? null),
            'customer_id' => $this->nullableString($row['customerID'] ?? null),
            'customer_name' => $this->nullableString($row['customerName'] ?? null),
            'customer_address' => $this->nullableString($row['customerAddress'] ?? null),
            'total' => is_numeric($row['productSalesTotal'] ?? null) ? (float) $row['productSalesTotal'] : null,
            'currency_sign' => $this->nullableString($row['currencySign'] ?? null),
            'status_id' => $this->nullableString($row['statusID'] ?? null),
            'status_name' => $this->nullableString($row['statusName'] ?? null),
            'created_by' => $this->nullableString($row['createdBy'] ?? null),
            'link_purchase_number' => $this->nullableString($row['linkPurchaseNum'] ?? null),
            'additional_info' => $this->nullableString($row['additionalInfo'] ?? null),
            'raw' => $row,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        return filled($value) ? (string) $value : null;
    }
}
