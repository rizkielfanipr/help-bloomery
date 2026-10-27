<?php

namespace App\Actions\Rnd\Material;

use App\Enums\MaterialEsbSyncStatus;
use App\Enums\MaterialPricingStatus;
use App\Models\RndMaterialPricingRequest;
use App\Models\User;
use App\Services\Rnd\Material\MaterialAuditLogger;
use App\Services\Rnd\Material\MaterialWorkflowNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Finance reports a wrong Product Detail/UOM mapping (§17.5, §25): the request fails with the
 * reason and the material goes back to IT reconciliation. Finance never changes the ID itself.
 */
class ReturnMaterialPricingToItAction
{
    use ManagesPricingRequest;

    public function __construct(private readonly MaterialAuditLogger $audit, private readonly MaterialWorkflowNotifier $notifier) {}

    public function execute(RndMaterialPricingRequest $request, User $actor, string $reason): RndMaterialPricingRequest
    {
        if (mb_strlen(trim($reason)) < 10) {
            throw ValidationException::withMessages(['reason' => 'Jelaskan masalah mapping minimal 10 karakter.']);
        }

        return DB::transaction(function () use ($request, $actor, $reason): RndMaterialPricingRequest {
            $request = $this->lockForFinance($request, $actor, [MaterialPricingStatus::ReadyForPricing, MaterialPricingStatus::DraftPricing, MaterialPricingStatus::VerificationRequired]);
            $request->fill(['status' => MaterialPricingStatus::Failed, 'failure_reason' => 'Mapping dikembalikan ke IT: '.$reason, 'active_key' => null])->save();

            $material = $request->material;
            $material->forceFill([
                'pricing_status' => MaterialPricingStatus::Failed,
                'esb_sync_status' => MaterialEsbSyncStatus::NeedsReconciliation,
                'status' => MaterialEsbSyncStatus::NeedsReconciliation->legacyStatus(),
                'sync_error' => 'Finance: mapping Product Detail perlu diperiksa. '.$reason,
            ])->save();
            $this->audit->event($material, 'pricing_returned_to_it', $actor, $reason, metadata: ['pricing_request' => $request->uuid, 'product_detail_id' => $request->product_detail_id]);
            $this->notifier->notify($material, 'pricing_returned', 'Mapping Pricelist dikembalikan ke IT: '.$material->product_code, $reason);

            return $request;
        });
    }
}
