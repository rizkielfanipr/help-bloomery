<?php

namespace App\Actions\StoreSalesOrder;

use App\Models\Branch;
use App\Models\BranchEsbCode;

/**
 * Resolves a Branch's ESB mapping for Store Sales Order using the same single source Stock Card
 * and R&D Internal Memo already share (`Branch::activeStockCardEsbCode()`, unified in commit
 * `8b982a1`) — docs/store-sales-order-prd.md §8.2 explicitly asks to follow whichever resolver is
 * current in the repository rather than add a new per-feature mapping field. This class only adds
 * the completeness/credential checks specific to Store Sales Order (numeric ESB Branch ID and an
 * ESB Core login credential, since this feature calls ESB Core directly — not the static Master
 * Menu token R&D Internal Memo's catalog uses).
 */
class ResolveSalesOrderBranchMappingAction
{
    public function resolve(Branch $branch): SalesOrderBranchMappingResolution
    {
        $mapping = $branch->activeStockCardEsbCode();

        if (! $mapping) {
            return SalesOrderBranchMappingResolution::blocked(
                $branch,
                'Mapping ESB belum dipilih atau tidak aktif. Atur melalui Master Branch.',
            );
        }

        return $this->validate($branch, $mapping);
    }

    /** @param  iterable<int, Branch>  $branches @return array<int, SalesOrderBranchMappingResolution> keyed by Branch id */
    public function resolveMany(iterable $branches): array
    {
        $result = [];
        foreach ($branches as $branch) {
            $result[$branch->id] = $this->resolve($branch);
        }

        return $result;
    }

    private function validate(Branch $branch, BranchEsbCode $mapping): SalesOrderBranchMappingResolution
    {
        if (blank($mapping->esb_comcode)) {
            return SalesOrderBranchMappingResolution::blocked($branch, 'Company Code pada mapping ESB Branch ini masih kosong.');
        }

        if (blank($mapping->esb_branch_code)) {
            return SalesOrderBranchMappingResolution::blocked($branch, 'Branch Code pada mapping ESB Branch ini masih kosong.');
        }

        if (! is_int($mapping->esb_branch_id) || $mapping->esb_branch_id < 1) {
            return SalesOrderBranchMappingResolution::blocked($branch, 'Numeric ESB Branch ID pada mapping ini belum tersedia.');
        }

        $companyCode = strtoupper(trim($mapping->esb_comcode));
        $credentials = (array) config("esb.core.companies.{$companyCode}", []);

        if (blank($credentials['username'] ?? null) || blank($credentials['password'] ?? null)) {
            return SalesOrderBranchMappingResolution::blocked($branch, "Credential ESB Core {$companyCode} belum dikonfigurasi.");
        }

        return SalesOrderBranchMappingResolution::resolved($branch, $mapping);
    }
}
