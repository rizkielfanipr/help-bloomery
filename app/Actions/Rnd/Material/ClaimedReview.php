<?php

namespace App\Actions\Rnd\Material;

use App\Enums\MaterialReviewStatus;
use App\Models\RndMaterialReview;
use App\Models\RndProductEsbMaterial;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Locks and returns the review currently claimed by the acting IT reviewer, refusing decisions on
 * stale versions or reviews claimed by someone else.
 */
trait ClaimedReview
{
    /**
     * @return array{0: RndProductEsbMaterial, 1: RndMaterialReview}
     *
     * @throws ValidationException
     */
    protected function lockClaimedReview(RndProductEsbMaterial $material, User $actor, ?int $expectedVersion = null): array
    {
        $material = RndProductEsbMaterial::query()->lockForUpdate()->findOrFail($material->id);
        if ($expectedVersion !== null && $expectedVersion !== $material->submission_version) {
            throw ValidationException::withMessages(['review' => 'Versi pengajuan sudah berubah. Muat ulang halaman review.']);
        }

        $review = RndMaterialReview::query()->lockForUpdate()->where('rnd_product_esb_material_id', $material->id)
            ->where('submission_version', $material->submission_version)->first();

        if (! $review || $review->status !== MaterialReviewStatus::UnderItReview) {
            throw ValidationException::withMessages(['review' => 'Ambil review terlebih dahulu sebelum mengambil keputusan.']);
        }

        if ($review->claimed_by !== $actor->id) {
            throw ValidationException::withMessages(['review' => 'Review ini sedang ditangani IT lain.']);
        }

        return [$material, $review];
    }
}
