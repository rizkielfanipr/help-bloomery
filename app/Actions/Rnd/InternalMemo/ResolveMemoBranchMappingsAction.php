<?php

namespace App\Actions\Rnd\InternalMemo;

use App\Models\Branch;
use App\Models\BranchEsbCode;

/**
 * Resolves the same explicit ESB mapping selected for Stock Card. Both features therefore use a
 * single source on Master Branch and never guess from the first or only active mapping.
 */
class ResolveMemoBranchMappingsAction
{
    public function resolve(Branch $branch): MemoBranchMappingResolution
    {
        $explicit = $branch->activeStockCardEsbCode();
        if ($explicit) {
            return $this->validate($branch, $explicit);
        }

        return MemoBranchMappingResolution::blocked(
            $branch,
            'Sumber Stock Card & Memo Internal belum dipilih atau mapping-nya tidak aktif. Atur melalui Master Branch.',
        );
    }

    /** @param  iterable<int, Branch>  $branches @return array<int, MemoBranchMappingResolution> keyed by Branch id */
    public function resolveMany(iterable $branches): array
    {
        $result = [];
        foreach ($branches as $branch) {
            $result[$branch->id] = $this->resolve($branch);
        }

        return $result;
    }

    private function validate(Branch $branch, BranchEsbCode $mapping): MemoBranchMappingResolution
    {
        if (blank($mapping->esb_comcode)) {
            return MemoBranchMappingResolution::blocked($branch, 'Company Code pada mapping ESB Branch ini masih kosong.');
        }

        if (blank($mapping->esb_branch_code)) {
            return MemoBranchMappingResolution::blocked($branch, 'Branch Code pada mapping ESB Branch ini masih kosong.');
        }

        $companyCode = strtoupper(trim($mapping->esb_comcode));

        if (blank((string) config('esb.tokens.'.$companyCode, ''))) {
            return MemoBranchMappingResolution::blocked($branch, "Static token Master Menu {$companyCode} belum tersedia.");
        }

        return MemoBranchMappingResolution::resolved($branch, $mapping);
    }
}
