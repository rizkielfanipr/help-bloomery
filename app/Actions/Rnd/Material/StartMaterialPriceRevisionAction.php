<?php

namespace App\Actions\Rnd\Material;

use App\Enums\MaterialPricingStatus;
use App\Models\RndMaterialPricingRequest;
use App\Models\User;
use App\Services\Rnd\Material\MaterialAuditLogger;
use App\Services\Rnd\Material\MaterialPricingEligibility;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Starts a price revision (§25 "Harga direvisi"): the priced request stays as history and a new
 * revision request is queued. The workflow completion is explicitly reopened until the new price
 * is verified.
 */
class StartMaterialPriceRevisionAction
{
    use ManagesPricingRequest;

    public function __construct(private readonly MaterialAuditLogger $audit, private readonly MaterialPricingEligibility $eligibility) {}

    public function execute(RndMaterialPricingRequest $request, User $actor, string $reason): RndMaterialPricingRequest
    {
        if (mb_strlen(trim($reason)) < 10) {
            throw ValidationException::withMessages(['reason' => 'Alasan revisi harga wajib diisi minimal 10 karakter.']);
        }

        return DB::transaction(function () use ($request, $actor, $reason): RndMaterialPricingRequest {
            $request = $this->lockForFinance($request, $actor, [MaterialPricingStatus::Priced]);
            $latest = (int) RndMaterialPricingRequest::query()->where('rnd_product_esb_material_id', $request->rnd_product_esb_material_id)->max('revision');
            $material = $request->material;
            $material->forceFill(['workflow_completed_at' => null])->save();
            $this->audit->event($material, 'pricing_revision_started', $actor, $reason, MaterialPricingStatus::Priced->value, MaterialPricingStatus::ReadyForPricing->value, ['previous_request' => $request->uuid]);

            $new = $this->eligibility->ensure($material->refresh(), $actor, $latest + 1);
            if (! $new) {
                throw ValidationException::withMessages(['reason' => 'Bahan tidak lagi memenuhi syarat Pricelist; periksa status ESB/sampel.']);
            }

            return $new;
        });
    }
}
