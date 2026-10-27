<?php

namespace App\Services\Rnd\Material;

use App\Models\RndProductEsbMaterial;
use App\Models\RndProductEsbMaterialUnit;

/**
 * Maps every active local unit to exactly one active Product Detail read back from ESB
 * (§13.3). A unit matches by its stored Product Detail ID (same UOM) or, when it has none yet, by
 * SKU + UOM ID. Missing, ambiguous, or shared matches are reported and the material is not synced.
 *
 * ESB `GET /product/{id}` does not echo the Product ID, so the caller owns the product identity
 * (the ID it requested or created); only the Product Details are read from the response.
 */
class MaterialDetailMapper
{
    /**
     * @param  array<string, mixed>  $remoteProduct
     * @return array{ok: bool, mapping: array<int, int>, errors: list<string>}
     */
    public function map(RndProductEsbMaterial $material, array $remoteProduct): array
    {
        $remoteDetails = collect($remoteProduct['productDetails'] ?? [])
            ->filter(fn ($detail): bool => is_array($detail) && (int) ($detail['productDetailID'] ?? 0) > 0)
            ->filter(fn (array $detail): bool => $this->isActive($detail))
            ->values();

        $mapping = [];
        $errors = [];
        $units = $material->units()->get()->filter(fn (RndProductEsbMaterialUnit $unit): bool => (bool) $unit->flag_active);

        if ($units->isEmpty()) {
            $errors[] = 'Bahan tidak mempunyai unit aktif.';
        }

        foreach ($units as $unit) {
            $candidates = $unit->esb_product_detail_id
                ? $remoteDetails->filter(fn (array $detail): bool => (int) $detail['productDetailID'] === (int) $unit->esb_product_detail_id
                    && $this->uomId($detail) === (int) $unit->uom_id)
                : collect();

            if ($candidates->isEmpty()) {
                $candidates = $remoteDetails->filter(fn (array $detail): bool => $this->uomId($detail) === (int) $unit->uom_id
                    && mb_strtolower(trim((string) ($detail['sku'] ?? $detail['SKU'] ?? ''))) === mb_strtolower(trim((string) $unit->sku)));
            }

            if ($candidates->count() !== 1) {
                $errors[] = $candidates->isEmpty()
                    ? "Unit {$unit->uom_name} (SKU {$unit->sku}) belum ditemukan sebagai Product Detail aktif di ESB."
                    : "Unit {$unit->uom_name} (SKU {$unit->sku}) cocok dengan lebih dari satu Product Detail aktif.";

                continue;
            }

            $mapping[$unit->id] = (int) $candidates->first()['productDetailID'];
        }

        $duplicates = collect($mapping)->duplicates();
        if ($duplicates->isNotEmpty()) {
            $errors[] = 'Dua unit lokal memetakan Product Detail yang sama: '.$duplicates->unique()->implode(', ').'.';
        }

        return ['ok' => $errors === [], 'mapping' => $errors === [] ? $mapping : [], 'errors' => $errors];
    }

    /** @param array<string, mixed> $detail */
    private function isActive(array $detail): bool
    {
        return ! array_key_exists('flagActive', $detail)
            || in_array(mb_strtolower((string) $detail['flagActive']), ['1', 'true', 'yes'], true);
    }

    /** @param array<string, mixed> $detail */
    private function uomId(array $detail): int
    {
        return (int) ($detail['uomID'] ?? $detail['unitID'] ?? 0);
    }
}
