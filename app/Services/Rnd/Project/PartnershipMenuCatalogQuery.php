<?php

namespace App\Services\Rnd\Project;

use App\Models\RndInternalMemoMenuCatalog;
use App\Services\Rnd\InternalMemo\InternalMemoCatalogContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PartnershipMenuCatalogQuery
{
    public function __construct(private readonly InternalMemoCatalogContext $context) {}

    public function paginate(string $name = '', string $code = '', int $page = 1, int $perPage = 10): LengthAwarePaginator
    {
        return $this->activeCatalog()
            ->when(trim($name) !== '', fn (Builder $query) => $query->where('menu_name', 'like', '%'.trim($name).'%'))
            ->when(trim($code) !== '', fn (Builder $query) => $query->where('menu_code', 'like', '%'.trim($code).'%'))
            ->orderBy('menu_name')
            ->orderBy('menu_id')
            ->paginate(min(20, max(1, $perPage)), ['*'], 'page', max(1, $page));
    }

    /** @return Builder<RndInternalMemoMenuCatalog> */
    public function selectable(): Builder
    {
        return $this->activeCatalog()->where('bom_id', '>', 0);
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
