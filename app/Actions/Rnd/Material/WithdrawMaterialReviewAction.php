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
 * R&D withdraws a submission that IT has not claimed yet (`review_cancelled`, §11.1). Nothing was
 * sent to ESB; the material becomes editable again.
 */
class WithdrawMaterialReviewAction
{
    public function __construct(private readonly MaterialAuditLogger $audit) {}

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function execute(RndProductEsbMaterial $material, User $actor, string $reason): void
    {
        if (! Gate::forUser($actor)->allows('submitForReview', $material)) {
            throw new AuthorizationException('Anda tidak berhak membatalkan pengajuan ini.');
        }

        DB::transaction(function () use ($material, $actor, $reason): void {
            $material = RndProductEsbMaterial::query()->lockForUpdate()->findOrFail($material->id);
            $review = RndMaterialReview::query()->lockForUpdate()->where('rnd_product_esb_material_id', $material->id)
                ->where('submission_version', $material->submission_version)->first();

            if (! $review || $review->status !== MaterialReviewStatus::WaitingItReview) {
                throw ValidationException::withMessages(['review' => 'Pengajuan hanya dapat dibatalkan sebelum diambil IT.']);
            }

            $review->forceFill(['status' => MaterialReviewStatus::ReviewCancelled, 'decision' => RndMaterialReview::DECISION_CANCEL, 'reason' => $reason, 'active_material_id' => null, 'reviewed_by' => $actor->id, 'reviewed_at' => now()])->save();
            $material->forceFill(['review_status' => MaterialReviewStatus::ReviewCancelled])->save();
            $this->audit->event($material, 'review_withdrawn', $actor, $reason, MaterialReviewStatus::WaitingItReview->value, MaterialReviewStatus::ReviewCancelled->value, review: $review);
        });
    }
}
