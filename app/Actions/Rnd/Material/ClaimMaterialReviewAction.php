<?php

namespace App\Actions\Rnd\Material;

use App\Enums\MaterialReviewStatus;
use App\Models\RndMaterialReview;
use App\Models\RndProductEsbMaterial;
use App\Models\User;
use App\Services\Rnd\Material\MaterialAuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * IT explicitly takes a waiting review (§12.5) so two reviewers never decide on the same version.
 * Claiming again by the same reviewer is idempotent.
 */
class ClaimMaterialReviewAction
{
    public function __construct(private readonly MaterialAuditLogger $audit) {}

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function execute(RndProductEsbMaterial $material, User $actor): RndMaterialReview
    {
        if (! Gate::forUser($actor)->allows('reviewAsIt', $material)) {
            throw new AuthorizationException('Anda tidak berhak mereview Master Bahan.');
        }

        return DB::transaction(function () use ($material, $actor): RndMaterialReview {
            $material = RndProductEsbMaterial::query()->lockForUpdate()->findOrFail($material->id);
            $review = RndMaterialReview::query()->lockForUpdate()->where('rnd_product_esb_material_id', $material->id)
                ->where('submission_version', $material->submission_version)->first();

            if ($review?->status === MaterialReviewStatus::UnderItReview) {
                if ($review->claimed_by === $actor->id) {
                    return $review;
                }

                throw ValidationException::withMessages(['review' => 'Pengajuan sedang direview oleh '.($review->claimer?->name ?? 'IT lain').'.']);
            }

            if ($review?->status !== MaterialReviewStatus::WaitingItReview) {
                throw ValidationException::withMessages(['review' => 'Tidak ada pengajuan yang menunggu review untuk bahan ini.']);
            }

            $review->forceFill(['status' => MaterialReviewStatus::UnderItReview, 'claimed_by' => $actor->id, 'claimed_at' => now()])->save();
            $material->forceFill(['review_status' => MaterialReviewStatus::UnderItReview])->save();
            $this->audit->event($material, 'review_claimed', $actor, from: MaterialReviewStatus::WaitingItReview->value, to: MaterialReviewStatus::UnderItReview->value, review: $review);

            return $review;
        });
    }
}
