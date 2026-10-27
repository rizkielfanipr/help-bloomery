<?php

namespace App\Actions\Rnd\Material;

use App\Enums\MaterialEsbSyncStatus;
use App\Enums\MaterialReviewStatus;
use App\Models\RndMaterialReview;
use App\Models\RndProductEsbMaterial;
use App\Models\User;
use App\Services\Rnd\Material\MaterialAuditLogger;
use App\Services\Rnd\Material\MaterialSnapshot;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * IT approves the claimed submission for a new ESB product and immediately starts the gated push
 * (§10.1 step 7–8). The approval is bound to the current version and data hash; a stale version is
 * refused. The push itself runs outside this transaction.
 */
class ApproveMaterialForEsbAction
{
    use ClaimedReview;

    public function __construct(
        private readonly MaterialSnapshot $snapshot,
        private readonly MaterialAuditLogger $audit,
        private readonly SyncMaterialToEsbAction $sync,
    ) {}

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function execute(RndProductEsbMaterial $material, User $actor, ?int $expectedVersion = null, ?string $note = null): RndProductEsbMaterial
    {
        if (! Gate::forUser($actor)->allows('approveEsb', $material)) {
            throw new AuthorizationException('Anda tidak berhak menyetujui push ESB.');
        }

        DB::transaction(function () use ($material, $actor, $expectedVersion, $note): void {
            [$material, $review] = $this->lockClaimedReview($material, $actor, $expectedVersion);

            if (! hash_equals($review->snapshot_hash, $this->snapshot->hashFor($material))) {
                throw ValidationException::withMessages(['review' => 'Data bahan berubah sejak diajukan. Kembalikan ke R&D atau perbarui snapshot melalui edit teknis.']);
            }

            $review->forceFill([
                'status' => MaterialReviewStatus::ApprovedForEsb,
                'decision' => RndMaterialReview::DECISION_APPROVE_CREATE,
                'reason' => $note,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'active_material_id' => null,
            ])->save();
            $material->forceFill([
                'review_status' => MaterialReviewStatus::ApprovedForEsb,
                'esb_sync_status' => MaterialEsbSyncStatus::Queued,
                'status' => MaterialEsbSyncStatus::Queued->legacyStatus(),
            ])->save();
            $this->audit->event($material, 'approved_for_esb', $actor, $note, MaterialReviewStatus::UnderItReview->value, MaterialReviewStatus::ApprovedForEsb->value, ['submission_version' => $review->submission_version], $review);
        });

        return $this->sync->execute($material->refresh(), $actor);
    }
}
