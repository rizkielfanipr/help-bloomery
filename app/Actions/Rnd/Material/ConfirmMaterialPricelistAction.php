<?php

namespace App\Actions\Rnd\Material;

use App\Enums\MaterialPricingStatus;
use App\Models\MaterialSourcingApproval;
use App\Models\RndMaterialPricingRequest;
use App\Models\User;
use App\Services\Rnd\Material\MaterialAuditLogger;
use App\Services\Rnd\Material\MaterialWorkflowCompletion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Manual controlled verification (§18.2 steps 2–4): Finance confirms that the drafted price was
 * entered in ESB, giving the ESB reference. This confirmation is the Finance approval (§2 item 11)
 * — no second identical approval exists. Only now does the request become `priced`.
 */
class ConfirmMaterialPricelistAction
{
    use ManagesPricingRequest;

    public function __construct(private readonly MaterialAuditLogger $audit, private readonly MaterialWorkflowCompletion $completion) {}

    /**
     * @param  array{external_reference: string, confirmed: bool, note?: ?string}  $data
     */
    public function execute(RndMaterialPricingRequest $request, User $actor, array $data): RndMaterialPricingRequest
    {
        $validated = Validator::make($data, [
            'external_reference' => ['required', 'string', 'min:3', 'max:120'],
            'confirmed' => ['accepted'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'external_reference.required' => 'Referensi Pricelist ESB wajib diisi sebagai bukti.',
            'confirmed.accepted' => 'Centang konfirmasi bahwa harga sudah tercatat di ESB.',
        ])->validate();

        $request = DB::transaction(function () use ($request, $actor, $validated): RndMaterialPricingRequest {
            $request = $this->lockForFinance($request, $actor, [MaterialPricingStatus::DraftPricing, MaterialPricingStatus::VerificationRequired]);
            if ($request->price === null || (float) $request->price <= 0) {
                throw ValidationException::withMessages(['external_reference' => 'Simpan draft harga terlebih dahulu.']);
            }
            if ($request->conflict_reason) {
                throw ValidationException::withMessages(['external_reference' => 'Selesaikan konflik pada request ini terlebih dahulu: '.$request->conflict_reason]);
            }

            $request->fill([
                'status' => MaterialPricingStatus::Priced,
                'verification_method' => RndMaterialPricingRequest::VERIFICATION_MANUAL,
                'external_reference' => $validated['external_reference'],
                'response_summary' => ['verification' => 'manual', 'note' => $validated['note'] ?? null],
                'verified_by' => $actor->id,
                'verified_at' => now(),
                'active_key' => null,
            ])->save();

            $material = $request->material;
            $material->forceFill(['pricing_status' => MaterialPricingStatus::Priced, 'finance_reviewed_by' => $actor->id, 'finance_reviewed_at' => now(), 'finance_note' => $validated['note'] ?? null])->save();
            MaterialSourcingApproval::query()->create([
                'rnd_product_esb_material_id' => $material->id,
                'stage' => 'finance',
                'action' => 'pricelist_confirmed',
                'actor_id' => $actor->id,
                'notes' => 'Pricelist '.$validated['external_reference'].($validated['note'] ?? null ? ' — '.$validated['note'] : ''),
            ]);
            $this->audit->event($material, 'pricelist_confirmed', $actor, $validated['note'] ?? null, MaterialPricingStatus::DraftPricing->value, MaterialPricingStatus::Priced->value, ['pricing_request' => $request->uuid, 'external_reference' => $validated['external_reference'], 'verification' => 'manual']);
            $this->completion->evaluate($material->refresh());

            return $request;
        });

        return $request;
    }
}
