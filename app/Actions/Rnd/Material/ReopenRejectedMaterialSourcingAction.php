<?php

namespace App\Actions\Rnd\Material;

use App\Enums\MaterialSourcingStatus;
use App\Models\RndProductEsbMaterial;
use App\Models\User;
use App\Services\Rnd\Material\MaterialAuditLogger;
use App\Services\Rnd\Material\MaterialWorkflowNotifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Reopens sourcing after R&D rejected the sample. Reject is terminal for Purchasing; only this
 * explicit step, with the reopen permission and a reason, returns the material to the Purchasing
 * queue. The rejected cycle stays in the history and the next hand-over opens a new cycle.
 */
class ReopenRejectedMaterialSourcingAction
{
    public function __construct(
        private readonly MaterialAuditLogger $audit,
        private readonly MaterialWorkflowNotifier $notifier,
    ) {}

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function execute(RndProductEsbMaterial $material, User $actor, string $reason): void
    {
        if (! Gate::forUser($actor)->allows('reopenSampleApproval', $material)) {
            throw new AuthorizationException('Anda tidak berhak membuka kembali sourcing.');
        }
        if (mb_strlen(trim($reason)) < 10) {
            throw ValidationException::withMessages(['reason' => 'Alasan buka kembali wajib diisi minimal 10 karakter.']);
        }

        DB::transaction(function () use ($material, $actor, $reason): void {
            $material = RndProductEsbMaterial::query()->lockForUpdate()->findOrFail($material->id);
            if (! $material->isInWorkflow() || $material->sourcing_status !== MaterialSourcingStatus::RejectedByRnd) {
                throw ValidationException::withMessages(['reason' => 'Hanya sourcing yang ditolak R&D yang dapat dibuka kembali.']);
            }

            $next = $material->sourcings()->exists() ? MaterialSourcingStatus::SourcingInProgress : MaterialSourcingStatus::WaitingPurchasing;
            $material->forceFill([
                'sourcing_status' => $next,
                'sourcing_selected_id' => null,
                'workflow_completed_at' => null,
            ])->save();

            $this->audit->event($material, 'sourcing_reopened', $actor, trim($reason), MaterialSourcingStatus::RejectedByRnd->value, $next->value);
            $this->notifier->notify($material, 'sourcing_reopened', 'Sourcing dibuka kembali: '.$material->product_code, 'R&D membuka kembali sourcing yang ditolak. Alasan: '.trim($reason));
        });
    }
}
