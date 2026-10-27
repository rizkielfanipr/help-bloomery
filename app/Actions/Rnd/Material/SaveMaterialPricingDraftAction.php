<?php

namespace App\Actions\Rnd\Material;

use App\Enums\MaterialPricingStatus;
use App\Models\RndMaterialPricingRequest;
use App\Models\User;
use App\Services\Rnd\Material\MaterialAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Finance saves the official price as a local draft (§18.2 step 1). Product, Product Detail, and
 * UOM are never taken from the form. A draft is not `priced`.
 */
class SaveMaterialPricingDraftAction
{
    use ManagesPricingRequest;

    public function __construct(private readonly MaterialAuditLogger $audit) {}

    /**
     * @param  array{price: float|string, company_code?: ?string, branch_scope?: ?string, effective_date?: ?string, finance_notes?: ?string}  $data
     */
    public function execute(RndMaterialPricingRequest $request, User $actor, array $data): RndMaterialPricingRequest
    {
        $validated = Validator::make($data, [
            'price' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'company_code' => ['nullable', 'string', Rule::in(array_keys((array) config('esb.core.companies', [])))],
            'branch_scope' => ['nullable', 'string', 'max:255'],
            'effective_date' => ['nullable', 'date'],
            'finance_notes' => ['nullable', 'string', 'max:2000'],
        ], ['price.gt' => 'Harga Pricelist harus lebih dari 0.'])->validate();

        return DB::transaction(function () use ($request, $actor, $validated): RndMaterialPricingRequest {
            $request = $this->lockForFinance($request, $actor, [MaterialPricingStatus::ReadyForPricing, MaterialPricingStatus::DraftPricing, MaterialPricingStatus::Failed]);
            if ($request->failure_reason && $request->status === MaterialPricingStatus::Failed) {
                $request->failure_reason = null;
            }

            $request->fill($validated + ['status' => MaterialPricingStatus::DraftPricing, 'drafted_by' => $actor->id, 'drafted_at' => now()])->save();
            $this->syncMaterialPricingStatus($request);
            $this->audit->event($request->material, 'pricing_draft_saved', $actor, metadata: ['pricing_request' => $request->uuid, 'price' => (float) $request->price]);

            return $request;
        });
    }
}
