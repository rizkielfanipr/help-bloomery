<?php

namespace App\Services\Rnd\Material;

use App\Enums\MaterialReviewStatus;
use App\Models\RndMaterialReview;
use App\Models\RndProductEsbMaterial;
use Illuminate\Validation\ValidationException;

/**
 * The single gate every ESB link/create/update of a Master Bahan passes (§2, §13): an IT approval
 * must exist for the material's current submission version and the exact data hash, and must not
 * have been used already.
 */
class MaterialEsbGate
{
    public function __construct(private readonly MaterialSnapshot $snapshot) {}

    /**
     * @throws ValidationException
     */
    public function approvedReview(RndProductEsbMaterial $material): RndMaterialReview
    {
        $review = RndMaterialReview::query()
            ->where('rnd_product_esb_material_id', $material->id)
            ->where('submission_version', $material->submission_version)
            ->first();

        if (! $review || $review->status !== MaterialReviewStatus::ApprovedForEsb || $material->review_status !== MaterialReviewStatus::ApprovedForEsb) {
            throw ValidationException::withMessages(['review' => 'Push/tautkan ke ESB memerlukan approval IT untuk versi data ini.']);
        }

        if ($review->consumed_at !== null) {
            throw ValidationException::withMessages(['review' => 'Approval IT versi ini sudah digunakan. Ajukan review baru untuk perubahan berikutnya.']);
        }

        if (! hash_equals($review->snapshot_hash, $this->snapshot->hashFor($material))) {
            throw ValidationException::withMessages(['review' => 'Data bahan berubah setelah disetujui IT. Approval tidak berlaku; ajukan review ulang.']);
        }

        return $review;
    }
}
