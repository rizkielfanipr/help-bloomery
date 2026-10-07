<?php

namespace App\Services\Rnd\InternalMemo;

use App\Models\Brand;
use App\Models\RndInternalMemo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Server-side Brand checks shared by create, edit Brand, and revision
 * (docs/rnd-internal-memo-brand-prd.md §8.1, §8.4, §19). The Brand must exist in Master Brand, and
 * one Brand may have only one active Memo per period and revision. The database unique index is
 * the final guard against races; this gives the user a clear message first.
 */
class InternalMemoBrandValidator
{
    public function brand(mixed $brandId, string $attribute = 'brand_id'): Brand
    {
        if (! is_numeric($brandId) || (int) $brandId < 1) {
            throw ValidationException::withMessages([$attribute => 'Pilih Brand Memo.']);
        }

        return Brand::query()->find((int) $brandId)
            ?? throw ValidationException::withMessages([$attribute => 'Brand yang dipilih tidak ditemukan.']);
    }

    public function ensureUniquePeriod(Brand $brand, string $periodMonth, int $revision, ?int $ignoreMemoId = null, string $attribute = 'period_month'): void
    {
        $exists = RndInternalMemo::query()
            ->where('brand_id', $brand->id)
            ->whereDate('period_month', Carbon::parse($periodMonth)->startOfMonth())
            ->where('revision', $revision)
            ->when($ignoreMemoId, fn ($query) => $query->whereKeyNot($ignoreMemoId))
            ->exists();

        if ($exists) {
            throw $this->duplicatePeriod($brand, $attribute);
        }
    }

    public function duplicatePeriod(Brand|string|null $brand, string $attribute = 'period_month'): ValidationException
    {
        $name = $brand instanceof Brand ? $brand->name : $brand;

        return ValidationException::withMessages([
            $attribute => filled($name)
                ? "Memo Brand {$name} untuk periode dan revisi ini sudah ada."
                : 'Memo Brand ini untuk periode dan revisi ini sudah ada.',
        ]);
    }
}
