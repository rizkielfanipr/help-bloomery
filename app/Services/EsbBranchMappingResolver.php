<?php

namespace App\Services;

use App\Models\BranchEsbCode;
use App\Models\GoodsReceipt;

class EsbBranchMappingResolver
{
    public function localBranchIdForReceipt(GoodsReceipt $goodsReceipt): ?int
    {
        if ($goodsReceipt->local_branch_id) {
            return (int) $goodsReceipt->local_branch_id;
        }

        return $this->resolve(
            $goodsReceipt->company_code,
            (int) $goodsReceipt->esb_branch_id,
        )?->branch_id;
    }

    public function resolve(string $companyCode, int $esbBranchId, ?string $esbBranchCode = null): ?BranchEsbCode
    {
        $mapping = BranchEsbCode::query()
            ->where('is_active', true)
            ->where('esb_comcode', $companyCode)
            ->where('esb_branch_id', $esbBranchId)
            ->first();

        if ($mapping || blank($esbBranchCode)) {
            return $mapping;
        }

        $mapping = BranchEsbCode::query()
            ->where('is_active', true)
            ->where('esb_comcode', $companyCode)
            ->where('esb_branch_code', $esbBranchCode)
            ->first();

        if ($mapping && $mapping->esb_branch_id !== $esbBranchId) {
            $mapping->update(['esb_branch_id' => $esbBranchId]);
        }

        return $mapping;
    }

    /**
     * Read-only lookup of optional local branch metadata (docs/products/inventory/receiving-central-blss/prd.md §9).
     * An exact active company + ESB branch ID match wins; the branch code fallback is used only
     * when exactly one active mapping matches. Unlike resolve(), it never updates a mapping.
     */
    public function lookup(string $companyCode, int $esbBranchId, ?string $esbBranchCode = null): ?BranchEsbCode
    {
        $mapping = BranchEsbCode::query()
            ->where('is_active', true)
            ->where('esb_comcode', $companyCode)
            ->where('esb_branch_id', $esbBranchId)
            ->first();

        if ($mapping || blank($esbBranchCode)) {
            return $mapping;
        }

        $matches = BranchEsbCode::query()
            ->where('is_active', true)
            ->where('esb_comcode', $companyCode)
            ->where('esb_branch_code', $esbBranchCode)
            ->limit(2)
            ->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }
}
