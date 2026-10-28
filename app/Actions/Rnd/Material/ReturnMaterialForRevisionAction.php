<?php

namespace App\Actions\Rnd\Material;

use App\Enums\MaterialReviewStatus;
use App\Models\RndMaterialReview;
use App\Models\RndProductEsbMaterial;
use App\Models\User;
use App\Services\Rnd\Material\MaterialAuditLogger;
use App\Services\Rnd\Material\MaterialWorkflowNotifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * IT returns a submission to R&D with a mandatory reason (§10.1 step 7, §12.4).
 */
class ReturnMaterialForRevisionAction
{
    use ClaimedReview;

    public function __construct(private readonly MaterialAuditLogger $audit, private readonly MaterialWorkflowNotifier $notifier) {}

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function execute(RndProductEsbMaterial $material, User $actor, string $reason, ?int $expectedVersion = null): RndMaterialReview
    {
        if (! Gate::forUser($actor)->allows('reviewAsIt', $material)) {
            throw new AuthorizationException('Anda tidak berhak mereview Master Bahan.');
        }
        if (mb_strlen(trim($reason)) < 10) {
            throw ValidationException::withMessages(['reason' => 'Alasan pengembalian wajib diisi minimal 10 karakter.']);
        }

        return DB::transaction(function () use ($material, $actor, $reason, $expectedVersion): RndMaterialReview {
            [$material, $review] = $this->lockClaimedReview($material, $actor, $expectedVersion);

            $review->forceFill([
                'status' => MaterialReviewStatus::RevisionRequired,
                'decision' => RndMaterialReview::DECISION_RETURN,
                'reason' => $reason,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'active_material_id' => null,
            ])->save();
            $material->forceFill(['review_status' => MaterialReviewStatus::RevisionRequired])->save();

            $this->audit->event($material, 'returned_for_revision', $actor, $reason, MaterialReviewStatus::UnderItReview->value, MaterialReviewStatus::RevisionRequired->value, review: $review);
            $this->notifier->notify($material, 'revision_required', 'Master Bahan perlu revisi: '.$material->product_code, $reason, includeCreator: true);

            return $review;
        });
    }
}
