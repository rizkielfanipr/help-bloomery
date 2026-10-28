<?php

namespace App\Services\Rnd\Material;

use App\Models\RndProductEsbMaterial;
use App\Models\RndProductEsbMaterialUnit;

/**
 * Canonical, order-stable snapshot of the data IT reviews (identity, classification, units). Its
 * hash binds an IT approval to exactly this data: any later change makes the approval stale.
 */
class MaterialSnapshot
{
    /** @return array<string, mixed> */
    public function build(RndProductEsbMaterial $material): array
    {
        $units = $material->relationLoaded('units') ? $material->units : $material->units()->get();

        return [
            'product_code' => (string) $material->product_code,
            'product_name' => (string) $material->product_name,
            'section' => $material->materialSection(),
            'category_id' => $material->category_id !== null ? (int) $material->category_id : null,
            'category_name' => $material->category_name,
            'sub_category_id' => $material->sub_category_id !== null ? (int) $material->sub_category_id : null,
            'sub_category_name' => $material->sub_category_name,
            'notes' => $material->notes,
            'units' => $units
                ->sortBy(fn (RndProductEsbMaterialUnit $unit): string => sprintf('%d-%010d', $unit->is_base ? 0 : 1, (int) $unit->uom_id))
                ->values()
                ->map(fn (RndProductEsbMaterialUnit $unit): array => [
                    'uom_id' => (int) $unit->uom_id,
                    'uom_name' => (string) $unit->uom_name,
                    'sku' => (string) $unit->sku,
                    'conversion_factor' => number_format((float) $unit->conversion_factor, 4, '.', ''),
                    'base_price' => number_format((float) $unit->base_price, 4, '.', ''),
                    'is_base' => (bool) $unit->is_base,
                    'is_stock' => (bool) $unit->is_stock,
                    'is_purchase' => (bool) $unit->is_purchase,
                    'is_transfer' => (bool) $unit->is_transfer,
                    'is_sales' => (bool) $unit->is_sales,
                    'flag_active' => (bool) $unit->flag_active,
                ])
                ->all(),
        ];
    }

    /** @param array<string, mixed> $snapshot */
    public function hash(array $snapshot): string
    {
        return hash('sha256', (string) json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    }

    public function hashFor(RndProductEsbMaterial $material): string
    {
        return $this->hash($this->build($material));
    }
}
