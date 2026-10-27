<?php

namespace App\Actions\Rnd\Material;

use App\Enums\MaterialEsbSyncStatus;
use App\Enums\MaterialReviewStatus;
use App\Models\RndMaterialReview;
use App\Models\RndProductEsbMaterial;
use App\Models\User;
use App\Services\Rnd\Material\MaterialAuditLogger;
use App\Services\Rnd\Material\MaterialSnapshot;
use App\Services\Rnd\Material\MaterialSubmissionValidator;
use App\Services\Rnd\Material\MaterialWorkflowNotifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * R&D submits a Master Bahan/WIP for the mandatory IT review (§10.1 steps 3–5, §12.5).
 *
 * The submitted data is snapshotted with a stable hash and a new submission version; the material
 * is locked for R&D. Repeating the same submit while the identical version is still waiting is
 * idempotent: no second version and no second Telegram event. The Telegram delivery row is written
 * in the same transaction and its job is dispatched only after commit.
 */
class SubmitMaterialForItReviewAction
{
    public function __construct(
        private readonly MaterialSnapshot $snapshot,
        private readonly MaterialSubmissionValidator $validator,
        private readonly MaterialAuditLogger $audit,
        private readonly MaterialWorkflowNotifier $notifier,
    ) {}

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function execute(RndProductEsbMaterial $material, User $actor): RndMaterialReview
    {
        if (! Gate::forUser($actor)->allows('submitForReview', $material)) {
            throw new AuthorizationException('Anda tidak berhak mengirim bahan untuk review IT.');
        }

        return DB::transaction(function () use ($material, $actor): RndMaterialReview {
            $material = RndProductEsbMaterial::query()->lockForUpdate()->findOrFail($material->id);
            $snapshot = $this->snapshot->build($material);
            $hash = $this->snapshot->hash($snapshot);
            $current = $material->review_status ?? MaterialReviewStatus::Draft;

            if ($current->isActiveReview()) {
                $active = RndMaterialReview::query()->where('rnd_product_esb_material_id', $material->id)
                    ->where('submission_version', $material->submission_version)->first();
                if ($active && hash_equals($active->snapshot_hash, $hash)) {
                    return $active;
                }

                throw ValidationException::withMessages(['review' => 'Bahan sedang direview IT dan tidak dapat diubah.']);
            }

            if (! $current->isEditableByRnd()) {
                throw ValidationException::withMessages(['review' => 'Bahan sudah disetujui IT dan menunggu proses ESB.']);
            }

            if (in_array($material->effectiveEsbSyncStatus(), [MaterialEsbSyncStatus::Queued, MaterialEsbSyncStatus::Syncing, MaterialEsbSyncStatus::NeedsReconciliation], true)) {
                throw ValidationException::withMessages(['review' => 'Selesaikan proses/rekonsiliasi ESB sebelum mengajukan review baru.']);
            }

            $this->validator->validate($material);

            $version = $material->submission_version + 1;
            $review = RndMaterialReview::query()->create([
                'rnd_product_esb_material_id' => $material->id,
                'submission_version' => $version,
                'active_material_id' => $material->id,
                'status' => MaterialReviewStatus::WaitingItReview,
                'snapshot' => $snapshot,
                'snapshot_hash' => $hash,
                'submitted_by' => $actor->id,
                'submitted_at' => now(),
            ]);

            $material->forceFill([
                'review_status' => MaterialReviewStatus::WaitingItReview,
                'submission_version' => $version,
                'esb_sync_status' => $material->effectiveEsbSyncStatus(),
            ])->save();

            $this->audit->event($material, 'submitted_for_review', $actor, from: $current->value, to: MaterialReviewStatus::WaitingItReview->value, metadata: ['submission_version' => $version], review: $review);
            $this->notifier->telegramReviewSubmitted($material, $review, $actor);
            $this->notifier->notify($material, 'review_submitted', ($material->isWip() ? 'Review Master WIP Baru' : 'Review Master Bahan Baru').': '.$material->product_code, "{$material->product_name} diajukan oleh {$actor->name} (versi {$version}).");

            return $review;
        });
    }
}
