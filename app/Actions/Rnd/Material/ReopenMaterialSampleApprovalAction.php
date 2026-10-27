<?php

namespace App\Actions\Rnd\Material;

use App\Enums\MaterialPricingStatus;
use App\Enums\MaterialSampleCycleStatus;
use App\Enums\MaterialSourcingStatus;
use App\Models\RndMaterialPricingRequest;
use App\Models\RndMaterialSampleCycle;
use App\Models\RndProductEsbMaterial;
use App\Models\User;
use App\Services\Rnd\Material\MaterialAuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Reopens an approved sample (§16.4, §25). Only with the dedicated permission and a reason. A
 * Pricing Request Finance has not touched yet is cancelled; once pricing work started, reopening
 * is refused until Finance resolves the conflict — prices are never deleted silently.
 */
class ReopenMaterialSampleApprovalAction
{
    public function __construct(private readonly MaterialAuditLogger $audit) {}

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function execute(RndProductEsbMaterial $material, User $actor, string $reason): void
    {
        if (! Gate::forUser($actor)->allows('reopenSampleApproval', $material)) {
            throw new AuthorizationException('Anda tidak berhak membuka ulang approval sampel.');
        }
        if (mb_strlen(trim($reason)) < 10) {
            throw ValidationException::withMessages(['reason' => 'Alasan buka ulang wajib diisi minimal 10 karakter.']);
        }

        $conflict = DB::transaction(function () use ($material, $actor, $reason): ?RndMaterialPricingRequest {
            $material = RndProductEsbMaterial::query()->lockForUpdate()->findOrFail($material->id);
            if ($material->sourcing_status !== MaterialSourcingStatus::ApprovedByRnd) {
                throw ValidationException::withMessages(['reason' => 'Hanya sampel yang sudah disetujui R&D yang dapat dibuka ulang.']);
            }

            $pricing = RndMaterialPricingRequest::query()->lockForUpdate()->where('rnd_product_esb_material_id', $material->id)
                ->whereIn('status', [MaterialPricingStatus::DraftPricing->value, MaterialPricingStatus::Submitting->value, MaterialPricingStatus::VerificationRequired->value, MaterialPricingStatus::Priced->value])
                ->latest('id')->first();
            if ($pricing) {
                return $pricing;
            }

            RndMaterialPricingRequest::query()->where('rnd_product_esb_material_id', $material->id)
                ->where('status', MaterialPricingStatus::ReadyForPricing->value)
                ->update(['status' => MaterialPricingStatus::Cancelled->value, 'active_key' => null, 'conflict_reason' => 'Dibatalkan karena approval sampel dibuka ulang: '.$reason]);

            $cycle = RndMaterialSampleCycle::query()->where('rnd_product_esb_material_id', $material->id)
                ->where('status', MaterialSampleCycleStatus::Approved->value)->latest('cycle_number')->first();
            $cycle?->fill(['status' => MaterialSampleCycleStatus::Received, 'evaluation' => null, 'evaluation_reason' => null, 'evaluated_by' => null, 'evaluated_at' => null])->save();

            $material->forceFill([
                'sourcing_status' => MaterialSourcingStatus::SampleReceived,
                'sourcing_selected_id' => null,
                'pricing_status' => MaterialPricingStatus::NotReady,
                'workflow_completed_at' => null,
            ])->save();
            $this->audit->event($material, 'sample_approval_reopened', $actor, $reason, MaterialSourcingStatus::ApprovedByRnd->value, MaterialSourcingStatus::SampleReceived->value, ['cycle' => $cycle?->cycle_number]);

            return null;
        });

        if ($conflict) {
            // Recorded on its own so the refused reopen still leaves the conflict visible to Finance.
            DB::transaction(function () use ($conflict, $actor, $reason): void {
                $conflict->forceFill(['conflict_reason' => 'Permintaan buka ulang approval sampel: '.$reason])->save();
                $this->audit->event($conflict->material, 'sample_reopen_conflict', $actor, $reason, metadata: ['pricing_request' => $conflict->uuid, 'pricing_status' => $conflict->status->value]);
            });

            throw ValidationException::withMessages(['reason' => 'Pricelist sudah diproses Finance ('.$conflict->status->getLabel().'). Konflik dicatat; Finance/administrator harus menyelesaikannya sebelum approval dibuka ulang.']);
        }
    }
}
