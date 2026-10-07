<?php

namespace App\Services\Rnd\ShelfLife;

use App\Enums\RndWipShelfLifeStatus;
use App\Models\RndProjectProduct;

/**
 * Ready/Released gate for a Project Product (docs/rnd-wip-shelf-life-prd.md §9.4): every WIP the
 * mapping found must have an active master. A Product without WIP is never blocked; unresolved or
 * unidentified WIP and a failed mapping are blockers, never silently treated as complete. Computed
 * entirely server-side from stored BOM snapshots and the WIP mapping.
 */
class ProjectProductShelfLifeReadiness
{
    public function __construct(
        private readonly ProjectProductWipCollector $collector,
        private readonly WipShelfLifeResolver $resolver,
    ) {}

    /**
     * Human-readable blockers naming the affected WIP; empty when the Product may be Ready/Released.
     *
     * @return list<string>
     */
    public function blockers(RndProjectProduct $product): array
    {
        $collected = $this->collector->forProduct($product);

        if ($collected['error'] !== null) {
            return ["Kelengkapan Shelf Life WIP belum dapat diperiksa: {$collected['error']}"];
        }

        $masters = $this->resolver->masters(collect($collected['items'])->pluck('product_detail_id'));
        $blockers = [];

        foreach ($collected['items'] as $item) {
            $label = trim($item['product_code'].' '.$item['product_name']);
            $status = RndWipShelfLifeStatus::for($item['product_detail_id'], $masters->get($item['product_detail_id']));

            $blocker = match ($status) {
                RndWipShelfLifeStatus::IdentityIncomplete => "WIP {$label} belum mempunyai Product Detail ID.",
                RndWipShelfLifeStatus::Missing => "Shelf Life WIP {$label} belum diisi.",
                RndWipShelfLifeStatus::Inactive => "Shelf Life WIP {$label} tidak aktif.",
                RndWipShelfLifeStatus::Complete => null,
            };

            if ($blocker !== null) {
                $blockers[] = $blocker;
            }

            if (! $item['recipe_resolved'] && $item['product_detail_id'] !== null) {
                $blockers[] = "BOM turunan WIP {$label} tidak ditemukan, sehingga WIP di dalamnya belum dapat dipastikan.";
            }
        }

        return $blockers;
    }
}
