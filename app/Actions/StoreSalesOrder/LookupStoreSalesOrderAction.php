<?php

namespace App\Actions\StoreSalesOrder;

use App\Models\Branch;
use App\Models\BranchEsbCode;
use App\Services\EsbProductSalesService;
use Illuminate\Validation\ValidationException;

/**
 * docs/store-sales-order-prd.md §9, §10.1. Resolves the Branch's ESB mapping, then performs the
 * exact-match ESB Core lookup. Both the Create and Refresh flows share this (never do the raw HTTP
 * call themselves), so the "never trust the first row" guarantee lives in exactly one place
 * (EsbProductSalesService).
 */
class LookupStoreSalesOrderAction
{
    public function __construct(
        private readonly ResolveSalesOrderBranchMappingAction $mappingResolver,
        private readonly EsbProductSalesService $productSalesService,
    ) {}

    /** @return array{mapping: BranchEsbCode, snapshot: array<string, mixed>} */
    public function execute(Branch $branch, string $productSalesNumber): array
    {
        $resolution = $this->mappingResolver->resolve($branch);

        if (! $resolution->isResolved()) {
            throw ValidationException::withMessages([
                'product_sales_number' => $resolution->blockedReason,
            ]);
        }

        $mapping = $resolution->mapping;

        $snapshot = $this->productSalesService->exactLookup(
            strtoupper(trim($mapping->esb_comcode)),
            $mapping->esb_branch_id,
            $productSalesNumber,
        );

        if (! $snapshot) {
            throw ValidationException::withMessages([
                'product_sales_number' => 'Sales Order tidak ditemukan di ESB untuk Branch ini.',
            ]);
        }

        return ['mapping' => $mapping, 'snapshot' => $snapshot];
    }
}
