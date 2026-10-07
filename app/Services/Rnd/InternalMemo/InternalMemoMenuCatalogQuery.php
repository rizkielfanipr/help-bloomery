<?php

namespace App\Services\Rnd\InternalMemo;

use App\Models\RndInternalMemoMenuCatalog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reads the one global BLSS Master Menu snapshot shared by every Memo
 * (docs/rnd-internal-memo-brand-prd.md §14.3, §15.4). No Brand, Branch, or company filter exists:
 * the context is fixed server-side by InternalMemoCatalogContext. Before the first successful sync
 * there is no snapshot yet, so nothing is selectable.
 */
class InternalMemoMenuCatalogQuery
{
    public function __construct(private readonly InternalMemoCatalogContext $context) {}

    public function paginate(int $perPage = 10, string $name = '', string $code = '', int $page = 1): LengthAwarePaginator
    {
        return $this->activeCatalog()
            ->when(trim($name) !== '', fn (Builder $query) => $query->where('menu_name', 'like', '%'.trim($name).'%'))
            ->when(trim($code) !== '', fn (Builder $query) => $query->where('menu_code', 'like', '%'.trim($code).'%'))
            ->orderBy('menu_name')
            ->orderBy('menu_id')
            ->paginate(min(20, max(1, $perPage)), ['*'], 'page', max(1, $page));
    }

    /** An active catalog row of the BLSS context, or null when the Menu is not selectable. */
    public function findSelectable(int $menuId): ?RndInternalMemoMenuCatalog
    {
        return $this->activeCatalog()->where('menu_id', $menuId)->first();
    }

    /** @return Builder<RndInternalMemoMenuCatalog> */
    private function activeCatalog(): Builder
    {
        $branchCode = $this->context->catalogBranchCode();

        return RndInternalMemoMenuCatalog::query()
            ->where('company_code', $this->context->companyCode())
            ->when($branchCode === null, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when($branchCode !== null, fn (Builder $query) => $query->where('branch_code', $branchCode))
            ->where('flag_active', true);
    }
}
