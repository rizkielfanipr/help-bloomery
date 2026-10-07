<?php

namespace App\Services\Rnd\ShelfLife;

use App\Enums\RndShelfLifeUnit;
use App\Enums\RndStorageCondition;
use App\Models\RndBomCatalog;
use App\Models\RndProductEsbShelfLife;
use App\Models\RndWipProduct;
use Illuminate\Support\Collection;

/**
 * Read-only dry-run report over `rnd_esb_product_shelf_lives` before the WIP identity unique
 * constraint is relied on (docs/rnd-wip-shelf-life-prd.md §21.2). It never modifies data —
 * ambiguous rows are only reported so they can be resolved by an approved, separate step.
 */
class WipShelfLifeIdentityAudit
{
    /**
     * @return array{
     *     total: int,
     *     duplicate_identities: list<array{company_code: string, esb_product_detail_id: int, row_ids: list<int>}>,
     *     missing_product_detail_ids: list<int>,
     *     menu_only: list<int>,
     *     menu_and_product_detail: list<int>,
     *     unknown_units: list<array{id: int, value: string}>,
     *     legacy_units: list<array{id: int, value: string, maps_to: string}>,
     *     unknown_storage_conditions: list<array{id: int, value: string}>,
     *     trashed_identity_conflicts: list<array{company_code: string, esb_product_detail_id: int, row_ids: list<int>}>,
     *     unproven_wip_product_detail_ids: list<array{id: int, esb_product_detail_id: int}>,
     * }
     */
    public function report(): array
    {
        $rows = RndProductEsbShelfLife::withTrashed()->orderBy('id')->get();
        $withProductDetail = $rows->whereNotNull('esb_product_detail_id');
        $productDetailIds = $withProductDetail->pluck('esb_product_detail_id')->unique()->values();
        $knownCatalogProductDetailIds = RndBomCatalog::query()
            ->whereIn('product_detail_id', $productDetailIds)
            ->pluck('product_detail_id')
            ->merge(RndWipProduct::query()->whereIn('product_detail_id', $productDetailIds)->pluck('product_detail_id'))
            ->map(fn ($id): int => (int) $id)
            ->flip();

        return [
            'total' => $rows->count(),
            'duplicate_identities' => $this->duplicateIdentities($withProductDetail->whereNull('deleted_at')),
            'missing_product_detail_ids' => $rows->whereNull('esb_product_detail_id')->pluck('id')->values()->all(),
            'menu_only' => $rows->whereNull('esb_product_detail_id')->whereNotNull('esb_menu_id')->pluck('id')->values()->all(),
            'menu_and_product_detail' => $withProductDetail->whereNotNull('esb_menu_id')->pluck('id')->values()->all(),
            'unknown_units' => $rows
                ->filter(fn (RndProductEsbShelfLife $row): bool => RndShelfLifeUnit::fromLegacy($row->shelf_life_unit) === null)
                ->map(fn (RndProductEsbShelfLife $row): array => ['id' => $row->id, 'value' => (string) $row->shelf_life_unit])
                ->values()->all(),
            'legacy_units' => $rows
                ->filter(fn (RndProductEsbShelfLife $row): bool => RndShelfLifeUnit::tryFrom((string) $row->shelf_life_unit) === null
                    && RndShelfLifeUnit::fromLegacy($row->shelf_life_unit) !== null)
                ->map(fn (RndProductEsbShelfLife $row): array => [
                    'id' => $row->id,
                    'value' => (string) $row->shelf_life_unit,
                    'maps_to' => RndShelfLifeUnit::fromLegacy($row->shelf_life_unit)->value,
                ])
                ->values()->all(),
            'unknown_storage_conditions' => $rows
                ->filter(fn (RndProductEsbShelfLife $row): bool => RndStorageCondition::tryFrom((string) $row->storage_condition) === null)
                ->map(fn (RndProductEsbShelfLife $row): array => ['id' => $row->id, 'value' => (string) $row->storage_condition])
                ->values()->all(),
            'trashed_identity_conflicts' => $this->duplicateIdentities($withProductDetail, requireTrashedRow: true),
            'unproven_wip_product_detail_ids' => $withProductDetail
                ->reject(fn (RndProductEsbShelfLife $row): bool => $knownCatalogProductDetailIds->has($row->esb_product_detail_id))
                ->map(fn (RndProductEsbShelfLife $row): array => ['id' => $row->id, 'esb_product_detail_id' => $row->esb_product_detail_id])
                ->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, RndProductEsbShelfLife>  $rows
     * @return list<array{company_code: string, esb_product_detail_id: int, row_ids: list<int>}>
     */
    private function duplicateIdentities(Collection $rows, bool $requireTrashedRow = false): array
    {
        return $rows
            ->groupBy(fn (RndProductEsbShelfLife $row): string => $row->company_code.'|'.$row->esb_product_detail_id)
            ->filter(fn (Collection $group): bool => $group->count() > 1
                && (! $requireTrashedRow || $group->contains(fn (RndProductEsbShelfLife $row): bool => $row->trashed())))
            ->map(fn (Collection $group): array => [
                'company_code' => $group->first()->company_code,
                'esb_product_detail_id' => $group->first()->esb_product_detail_id,
                'row_ids' => $group->pluck('id')->values()->all(),
            ])
            ->values()
            ->all();
    }
}
