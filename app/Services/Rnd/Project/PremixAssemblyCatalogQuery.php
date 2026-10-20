<?php

namespace App\Services\Rnd\Project;

use App\Models\RndBomCatalog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PremixAssemblyCatalogQuery
{
    public function paginate(string $search = '', int $page = 1, int $perPage = 10): LengthAwarePaginator
    {
        return $this->selectable()
            ->search($search)
            ->orderBy('bom_name')
            ->orderBy('esb_bom_id')
            ->paginate(min(20, max(1, $perPage)), ['*'], 'page', max(1, $page));
    }

    /** @return Builder<RndBomCatalog> */
    public function selectable(): Builder
    {
        return RndBomCatalog::query()->where('bom_type_id', 1)->where('is_active', true);
    }
}
