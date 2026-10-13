<?php

namespace App\Services\Rnd\InternalMemo;

use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoMaterial;
use App\Models\RndProductEsbShelfLife;
use App\Services\Rnd\ShelfLife\WipShelfLifeResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * WIP Shelf Life masters of a Memo's product summary, shared by the Memo page and its xlsx export.
 * Masters are BLSS, so only WIPs of BLSS Menus are looked up — the Menu's company decides, not the
 * Memo-level column, which a legacy multi-branch Memo may still hold as another company.
 */
class InternalMemoShelfLifeLookup
{
    public function __construct(private readonly WipShelfLifeResolver $resolver) {}

    /** @return Collection<int, RndProductEsbShelfLife> keyed by Product Detail ID (non-base units resolve to their Product) */
    public function masters(RndInternalMemo $memo): Collection
    {
        return $this->resolver->masters($this->blssWipMaterials($memo)->pluck('esb_product_detail_id')->unique());
    }

    /** @return Builder<RndInternalMemoMaterial> */
    public function blssWipMaterials(RndInternalMemo $memo): Builder
    {
        return RndInternalMemoMaterial::query()
            ->whereIn('rnd_internal_memo_menu_id', $memo->menus()->where('company_code', RndInternalMemo::COMPANY_CODE)->select('id'))
            ->where('is_wip', true)
            ->whereNotNull('esb_product_detail_id');
    }
}
