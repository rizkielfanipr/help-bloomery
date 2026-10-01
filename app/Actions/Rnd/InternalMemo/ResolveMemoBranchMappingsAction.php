<?php

namespace App\Actions\Rnd\InternalMemo;

use App\Models\Branch;
use App\Models\BranchEsbCode;

/**
 * Resolves exactly one "primary" ESB mapping per Branch for R&D Internal Memo
 * (docs/rnd-internal-memo-multi-branch-prd.md §8). Resolution order, per the Phase 0 audit:
 *
 * 1. The explicit `internal_memo_esb_code_id` mapping on the Branch, if active.
 * 2. Stock Card's shared mapping — deliberately SKIPPED. Phase 0 decision #1 found
 *    `stock_card_esb_code_id` to be purpose-built and described as Stock-Card-only in its own
 *    admin UI copy; reusing it here would silently couple two independent business decisions for
 *    the same physical branch.
 * 3. If the Branch has exactly one active ESB mapping at all, use it.
 * 4. Otherwise (zero, or 2+ with no explicit pick), the Branch cannot be selected.
 *
 * Never picks "the first row by database order" at any step (PRD §8, §21).
 */
class ResolveMemoBranchMappingsAction
{
    public function resolve(Branch $branch): MemoBranchMappingResolution
    {
        $explicit = $branch->activeInternalMemoEsbCode();
        if ($explicit) {
            return $this->validate($branch, $explicit);
        }

        $active = $branch->activeEsbCodes();

        if ($active->count() === 1) {
            return $this->validate($branch, $active->first());
        }

        if ($active->count() > 1) {
            return MemoBranchMappingResolution::blocked(
                $branch,
                'Mapping ESB utama belum ditentukan untuk Branch ini. Pilih satu mapping aktif sebagai sumber Memo Internal melalui Master Branch.',
            );
        }

        return MemoBranchMappingResolution::blocked($branch, 'Branch ini belum mempunyai mapping ESB aktif.');
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
