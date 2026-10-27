<?php

namespace App\Actions\Rnd\Material;

use App\Enums\MaterialSampleCycleStatus;
use App\Enums\MaterialSourcingStatus;
use App\Models\MaterialSourcingApproval;
use App\Models\RndMaterialSampleCycle;
use App\Models\RndProductEsbMaterial;
use App\Models\User;
use App\Services\Rnd\Material\MaterialAuditLogger;
use App\Services\Rnd\Material\MaterialPricingEligibility;
use App\Services\Rnd\Material\MaterialWorkflowCompletion;
use App\Services\Rnd\Material\MaterialWorkflowNotifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * R&D evaluates a received sample (§16.4):
 *
 * - approve: supplier/sample selected and exactly one Pricing Request is created;
 * - resample: reason required, the cycle closes and the next cycle is opened (history kept);
 * - reject: reason required, no Pricing Request.
 */
class ReviewMaterialSampleAction
{
    public function __construct(
        private readonly MaterialAuditLogger $audit,
        private readonly MaterialWorkflowNotifier $notifier,
        private readonly MaterialPricingEligibility $pricing,
        private readonly MaterialWorkflowCompletion $completion,
    ) {}

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function execute(RndProductEsbMaterial $material, User $actor, string $decision, ?string $reason = null): RndMaterialSampleCycle
    {
        if (! Gate::forUser($actor)->allows('evaluateSample', $material)) {
            throw new AuthorizationException('Anda tidak berhak mengevaluasi sampel.');
        }
        if (! in_array($decision, [RndMaterialSampleCycle::EVALUATION_APPROVE, RndMaterialSampleCycle::EVALUATION_RESAMPLE, RndMaterialSampleCycle::EVALUATION_REJECT], true)) {
            throw ValidationException::withMessages(['decision' => 'Hasil evaluasi tidak dikenal.']);
        }
        if ($decision !== RndMaterialSampleCycle::EVALUATION_APPROVE && mb_strlen(trim((string) $reason)) < 10) {
            throw ValidationException::withMessages(['reason' => 'Alasan wajib diisi minimal 10 karakter untuk reject atau sampel ulang.']);
        }

        return DB::transaction(function () use ($material, $actor, $decision, $reason): RndMaterialSampleCycle {
            $material = RndProductEsbMaterial::query()->lockForUpdate()->findOrFail($material->id);
            $cycle = RndMaterialSampleCycle::query()->lockForUpdate()->where('rnd_product_esb_material_id', $material->id)
                ->where('status', MaterialSampleCycleStatus::Received->value)->latest('cycle_number')->first();
            if (! $cycle) {
                throw ValidationException::withMessages(['decision' => 'Sampel harus dikonfirmasi diterima R&D sebelum dievaluasi.']);
            }

            [$cycleStatus, $materialStatus] = match ($decision) {
                RndMaterialSampleCycle::EVALUATION_APPROVE => [MaterialSampleCycleStatus::Approved, MaterialSourcingStatus::ApprovedByRnd],
                RndMaterialSampleCycle::EVALUATION_RESAMPLE => [MaterialSampleCycleStatus::ResampleRequested, MaterialSourcingStatus::ResampleRequested],
                default => [MaterialSampleCycleStatus::Rejected, MaterialSourcingStatus::RejectedByRnd],
            };

            $cycle->fill(['status' => $cycleStatus, 'evaluation' => $decision, 'evaluation_reason' => $reason, 'evaluated_by' => $actor->id, 'evaluated_at' => now()])->save();

            $previous = $material->sourcing_status;
            $material->forceFill([
                'sourcing_status' => $materialStatus,
                'sourcing_selected_id' => $decision === RndMaterialSampleCycle::EVALUATION_APPROVE ? $cycle->material_sourcing_id : null,
                'rnd_reviewed_by' => $actor->id,
                'rnd_reviewed_at' => now(),
                'rnd_note' => $decision === RndMaterialSampleCycle::EVALUATION_APPROVE ? null : trim((string) $reason),
            ])->save();
            MaterialSourcingApproval::query()->create([
                'rnd_product_esb_material_id' => $material->id,
                'stage' => 'rnd',
                'action' => 'sample_'.$decision,
                'actor_id' => $actor->id,
                'notes' => $reason,
            ]);

            if ($decision === RndMaterialSampleCycle::EVALUATION_RESAMPLE) {
                RndMaterialSampleCycle::query()->create([
                    'rnd_product_esb_material_id' => $material->id,
                    'cycle_number' => $cycle->cycle_number + 1,
                    'status' => MaterialSampleCycleStatus::Prepared,
                ]);
                $this->notifier->notify($material, 'resample_requested', 'Sampel ulang diminta: '.$material->product_code, (string) $reason);
            }

            $this->audit->event($material, 'sample_'.$decision, $actor, $reason, $previous?->value, $materialStatus->value, ['cycle' => $cycle->cycle_number]);

            if ($decision === RndMaterialSampleCycle::EVALUATION_APPROVE) {
                $this->pricing->ensure($material->refresh(), $actor);
                $this->completion->evaluate($material->refresh());
            }

            return $cycle;
        });
    }
}
