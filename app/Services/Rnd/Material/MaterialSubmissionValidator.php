<?php

namespace App\Services\Rnd\Material;

use App\Models\RndProductEsbMaterial;
use App\Models\RndProductEsbMaterialUnit;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Automatic checks before a Master Bahan can be submitted to IT (§12.2). They help IT but never
 * replace the review.
 */
class MaterialSubmissionValidator
{
    /**
     * @throws ValidationException
     */
    public function validate(RndProductEsbMaterial $material): void
    {
        $errors = [];
        $units = $material->units()->get();

        if (blank($material->product_name)) {
            $errors['product_name'] = 'Nama produk wajib diisi.';
        }
        if (preg_match('/^[A-Z0-9][A-Z0-9\-]{1,49}$/', (string) $material->product_code) !== 1) {
            $errors['product_code'] = 'Kode produk wajib diisi dengan huruf kapital, angka, atau tanda hubung.';
        }
        if ((int) $material->category_id < 1 || (int) $material->sub_category_id < 1) {
            $errors['category_id'] = 'Kategori dan subkategori ESB wajib dipilih.';
        }
        if ($units->isEmpty()) {
            $errors['units'] = 'Minimal satu UOM wajib diisi.';
        }

        $base = $units->where('is_base', true);
        if ($base->count() !== 1 || abs((float) $base->first()?->conversion_factor - 1.0) > 0.000001) {
            $errors['units.base'] = 'Wajib ada tepat satu Base UOM dengan conversion factor 1.';
        }
        if ($units->contains(fn (RndProductEsbMaterialUnit $unit): bool => (int) $unit->uom_id < 1 || blank($unit->uom_name) || blank(trim((string) $unit->sku)) || (float) $unit->conversion_factor <= 0)) {
            $errors['units.rows'] = 'Setiap UOM wajib mempunyai unit, SKU, dan conversion factor lebih dari 0.';
        }
        if ($this->duplicates($units->pluck('uom_id'))) {
            $errors['units.uom'] = 'UOM tidak boleh dipilih lebih dari satu kali.';
        }
        if ($this->duplicates($units->map(fn (RndProductEsbMaterialUnit $unit): string => mb_strtoupper(trim((string) $unit->sku))))) {
            $errors['units.sku'] = 'SKU unit tidak boleh sama dalam satu produk.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Local records that may describe the same product: same code/SKU, or the same normalized name
     * under a different code (§12.3). Indicators only; IT decides.
     *
     * @return Collection<int, RndProductEsbMaterial>
     */
    public function duplicateCandidates(RndProductEsbMaterial $material): Collection
    {
        $name = mb_strtolower(trim((string) preg_replace('/\s+/', ' ', (string) $material->product_name)));
        $skus = $material->units()->pluck('sku')->push($material->sku)->filter()->unique()->values();

        return RndProductEsbMaterial::query()
            ->whereKeyNot($material->id)
            ->where(fn ($query) => $query
                ->where('product_code', $material->product_code)
                ->orWhere('esb_product_code', $material->product_code)
                ->orWhereRaw('lower(trim(product_name)) = ?', [$name])
                ->orWhereHas('units', fn ($units) => $units->whereIn('sku', $skus)))
            ->limit(10)
            ->get(['id', 'product_code', 'product_name', 'esb_product_id', 'status', 'esb_sync_status', 'review_status']);
    }

    /** @param Collection<int, mixed> $values */
    private function duplicates(Collection $values): bool
    {
        return $values->filter(fn ($value): bool => $value !== null && $value !== '')->duplicates()->isNotEmpty();
    }
}
