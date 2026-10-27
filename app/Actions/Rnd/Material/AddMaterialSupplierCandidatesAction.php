<?php

namespace App\Actions\Rnd\Material;

use App\Enums\MaterialSourcingStatus;
use App\Models\RndProductEsbMaterial;
use App\Models\User;
use App\Services\Rnd\Material\MaterialAuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Purchasing records supplier candidates (§15.1, existing fields kept unchanged).
 *
 * Workflow materials: only after the Sourcing Request exists (verified ESB sync); the status
 * moves to `sourcing_in_progress` and earlier candidates and cycles stay as history. Legacy rows
 * keep the existing behaviour (back to R&D review, previous selection cleared).
 */
class AddMaterialSupplierCandidatesAction
{
    private const WORKFLOW_OPEN = [
        MaterialSourcingStatus::WaitingPurchasing,
        MaterialSourcingStatus::SourcingInProgress,
        MaterialSourcingStatus::ResampleRequested,
        MaterialSourcingStatus::RejectedByRnd,
    ];

    public function __construct(private readonly MaterialAuditLogger $audit) {}

    /**
     * @param  list<array<string, mixed>>  $suppliers
     *
     * @throws AuthorizationException|ValidationException
     */
    public function execute(RndProductEsbMaterial $material, User $actor, array $suppliers): void
    {
        if (! $actor->can('submit material sourcing')) {
            throw new AuthorizationException('Anda tidak berhak mengelola sourcing bahan.');
        }

        DB::transaction(function () use ($material, $actor, $suppliers): void {
            $material = RndProductEsbMaterial::query()->lockForUpdate()->findOrFail($material->id);

            if ($material->isInWorkflow() && ! in_array($material->sourcing_status, self::WORKFLOW_OPEN, true)) {
                throw ValidationException::withMessages(['sourcings' => $material->requiresSourcing()
                    ? 'Supplier hanya dapat ditambahkan setelah Sourcing Request aktif dan sebelum sampel disetujui.'
                    : 'WIP tidak memerlukan sourcing supplier.']);
            }

            foreach ($suppliers as $row) {
                $material->sourcings()->create([...$row, 'submitted_by' => $actor->id]);
            }

            if ($material->isInWorkflow()) {
                $previous = $material->sourcing_status;
                if ($previous === MaterialSourcingStatus::WaitingPurchasing) {
                    $material->forceFill(['sourcing_status' => MaterialSourcingStatus::SourcingInProgress])->save();
                }
                $this->audit->event($material, 'supplier_candidates_added', $actor, from: $previous?->value, to: $material->sourcing_status->value, metadata: ['count' => count($suppliers)]);

                return;
            }

            $material->update([
                'sourcing_status' => MaterialSourcingStatus::PendingRndReview,
                'sourcing_selected_id' => null,
                'rnd_reviewed_by' => null,
                'rnd_reviewed_at' => null,
                'rnd_note' => null,
                'finance_reviewed_by' => null,
                'finance_reviewed_at' => null,
                'finance_note' => null,
            ]);
        });
    }
}
