<?php

namespace App\Services\Rnd\InternalMemo;

use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoMenuCatalog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InternalMemoMenuCatalogQuery
{
    public function paginate(
        RndInternalMemo $memo,
        int $perPage = 10,
        string $name = '',
        string $code = '',
        ?int $memoBranchId = null,
        ?string $companyCode = null,
        int $page = 1,
    ): LengthAwarePaginator {
        $contexts = $memo->branches()
            ->when($memoBranchId, fn ($query) => $query->whereKey($memoBranchId))
            ->when($companyCode, fn ($query) => $query->where('company_code_snapshot', mb_strtoupper(trim($companyCode))))
            ->get(['id', 'branch_name_snapshot', 'company_code_snapshot', 'branch_code_snapshot']);

        $query = RndInternalMemoMenuCatalog::query()
            ->where(function ($query) use ($contexts): void {
                $query->whereRaw('1 = 0');
                foreach ($contexts as $context) {
                    $query->orWhere(function ($query) use ($context): void {
                        $query->where('company_code', $context->company_code_snapshot)
                            ->where('branch_code', $context->branch_code_snapshot);
                    });
                }
            })
            ->when(trim($name) !== '', fn ($query) => $query->where('menu_name', 'like', '%'.trim($name).'%'))
            ->when(trim($code) !== '', fn ($query) => $query->where('menu_code', 'like', '%'.trim($code).'%'))
            ->where('flag_active', true)
            ->selectRaw('MIN(id) as id, company_code, menu_id, MAX(menu_code) as menu_code, MAX(menu_name) as menu_name, MAX(bom_id) as bom_id, MAX(bom_name) as bom_name, MAX(category_detail) as category_detail, 1 as flag_active, MAX(synced_at) as synced_at')
            ->groupBy('company_code', 'menu_id')
            ->orderByRaw('MAX(menu_name)')
            ->orderBy('company_code')
            ->orderBy('menu_id');

        return $query->paginate(min(20, max(1, $perPage)), ['*'], 'page', max(1, $page));
    }

    /** @return list<string> */
    public function branchNamesForMenu(RndInternalMemo $memo, string $companyCode, int $menuId): array
    {
        $availableCodes = RndInternalMemoMenuCatalog::query()
            ->where('company_code', $companyCode)
            ->where('menu_id', $menuId)
            ->pluck('branch_code');

        return $memo->branches()
            ->where('company_code_snapshot', $companyCode)
            ->whereIn('branch_code_snapshot', $availableCodes)
            ->pluck('branch_name_snapshot')
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<int> */
    public function memoBranchIdsForMenu(RndInternalMemo $memo, string $companyCode, int $menuId): array
    {
        $availableCodes = RndInternalMemoMenuCatalog::query()
            ->where('company_code', $companyCode)
            ->where('menu_id', $menuId)
            ->pluck('branch_code');

        return $memo->branches()
            ->where('company_code_snapshot', $companyCode)
            ->whereIn('branch_code_snapshot', $availableCodes)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
