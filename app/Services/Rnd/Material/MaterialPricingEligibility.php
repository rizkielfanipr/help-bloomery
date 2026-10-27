<?php

namespace App\Services\Rnd\Material;

use App\Enums\MaterialPricingStatus;
use App\Enums\MaterialSampleCycleStatus;
use App\Enums\MaterialSourcingStatus;
use App\Models\RndMaterialPricingRequest;
use App\Models\RndMaterialSampleCycle;
use App\Models\RndProductEsbMaterial;
use App\Models\RndProductEsbMaterialUnit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Single source of the Finance Pricelist eligibility rules (§17.2):
 *
 * - ordinary material: verified sync AND sourcing `approved_by_rnd` AND a target Product Detail;
 * - WIP: verified sync AND sourcing `not_required` AND a target Product Detail.
 *
 * `ensure()` creates the Pricing Request at most once per eligibility event (unique
 * `eligibility_key`) and keeps one active request per material (unique `active_key`).
 */
class MaterialPricingEligibility
{
    public function __construct(private readonly MaterialAuditLogger $audit, private readonly MaterialWorkflowNotifier $notifier) {}

    public function isEligible(RndProductEsbMaterial $material): bool
    {
        if (! $material->isInWorkflow() || ! $material->isVerifiedSynced() || ! $this->targetUnit($material)) {
            return false;
        }

        return $material->isWip()
            ? $material->sourcing_status === MaterialSourcingStatus::NotRequired
            : $material->sourcing_status === MaterialSourcingStatus::ApprovedByRnd;
    }

    /**
     * The unit Finance prices: the active purchase unit (a non-base purchase unit first), otherwise
     * the base unit. Default for open PRD decision §32 item 6.
     */
    public function targetUnit(RndProductEsbMaterial $material): ?RndProductEsbMaterialUnit
    {
        $units = $material->units()->get()->filter(fn (RndProductEsbMaterialUnit $unit): bool => $unit->flag_active && $unit->esb_product_detail_id);

        return $units->first(fn (RndProductEsbMaterialUnit $unit): bool => $unit->is_purchase && ! $unit->is_base)
            ?? $units->first(fn (RndProductEsbMaterialUnit $unit): bool => $unit->is_base)
            ?? null;
    }

    public function ensure(RndProductEsbMaterial $material, ?User $actor, int $revision = 1): ?RndMaterialPricingRequest
    {
        if (! $this->isEligible($material)) {
            return null;
        }

        $unit = $this->targetUnit($material);
        $cycle = $material->isWip() ? null : RndMaterialSampleCycle::query()
            ->where('rnd_product_esb_material_id', $material->id)
            ->where('status', MaterialSampleCycleStatus::Approved->value)
            ->latest('cycle_number')->first();
        $origin = $material->isWip() ? RndMaterialPricingRequest::ORIGIN_WIP : RndMaterialPricingRequest::ORIGIN_SOURCING;
        $eligibilityKey = sprintf('material:%d:v%d:%s:%s:detail:%d:r%d', $material->id, $material->submission_version, $origin, $cycle?->id ?? 'none', $unit->esb_product_detail_id, $revision);

        return DB::transaction(function () use ($material, $actor, $unit, $cycle, $origin, $eligibilityKey, $revision): RndMaterialPricingRequest {
            RndProductEsbMaterial::query()->lockForUpdate()->findOrFail($material->id);

            $existing = RndMaterialPricingRequest::query()->where('eligibility_key', $eligibilityKey)->lockForUpdate()->first();
            if ($existing && in_array($existing->status, [MaterialPricingStatus::Failed, MaterialPricingStatus::Cancelled], true)
                && ! RndMaterialPricingRequest::query()->where('active_key', $this->activeKey($material))->exists()) {
                // Same eligibility event again: IT re-verified the mapping Finance returned, or R&D
                // re-approved the same sample after a reopen. The request is reused, not duplicated.
                $existing->forceFill(['status' => MaterialPricingStatus::ReadyForPricing, 'active_key' => $this->activeKey($material), 'failure_reason' => null, 'conflict_reason' => null])->save();
                RndProductEsbMaterial::query()->whereKey($material->id)->update(['pricing_status' => MaterialPricingStatus::ReadyForPricing->value]);
                $this->audit->event($material, 'pricing_request_reactivated', $actor, metadata: ['pricing_request' => $existing->uuid]);

                return $existing;
            }
            if ($existing) {
                return $existing;
            }

            $active = RndMaterialPricingRequest::query()->where('active_key', $this->activeKey($material))->lockForUpdate()->first();
            if ($active && $active->product_detail_id === (int) $unit->esb_product_detail_id) {
                return $active;
            }
            if ($active && $active->status !== MaterialPricingStatus::ReadyForPricing) {
                $active->forceFill(['conflict_reason' => 'Product Detail target berubah ke '.$unit->esb_product_detail_id.' saat pricing berjalan; selesaikan atau batalkan request ini.'])->save();
                $this->audit->event($material, 'pricing_conflict', $actor, $active->conflict_reason);

                return $active;
            }
            if ($active) {
                $active->forceFill(['status' => MaterialPricingStatus::Cancelled, 'active_key' => null, 'conflict_reason' => 'Digantikan request baru karena Product Detail target berubah.'])->save();
            }

            $supplier = $cycle?->supplierCandidate;
            $request = RndMaterialPricingRequest::query()->create([
                'uuid' => (string) Str::uuid(),
                'rnd_product_esb_material_id' => $material->id,
                'esb_product_id' => $material->esb_product_id,
                'product_detail_id' => (int) $unit->esb_product_detail_id,
                'uom_id' => $unit->uom_id,
                'uom_name' => $unit->uom_name,
                'origin' => $origin,
                'rnd_material_sample_cycle_id' => $cycle?->id,
                'material_sourcing_id' => $supplier?->id,
                'supplier_name' => $supplier?->supplier_name,
                'offer_price' => $supplier?->price,
                'revision' => $revision,
                'eligibility_key' => $eligibilityKey,
                'active_key' => $this->activeKey($material),
                'status' => MaterialPricingStatus::ReadyForPricing,
                'idempotency_key' => 'pricing-request:'.$eligibilityKey,
                'queued_at' => now(),
            ]);

            RndProductEsbMaterial::query()->whereKey($material->id)->update([
                'pricing_status' => MaterialPricingStatus::ReadyForPricing->value,
                'workflow_completed_at' => null,
            ]);
            $this->audit->event($material, 'pricing_request_created', $actor, to: MaterialPricingStatus::ReadyForPricing->value, metadata: ['pricing_request' => $request->uuid, 'origin' => $origin, 'product_detail_id' => $request->product_detail_id]);
            $this->notifier->notify($material, 'pricing_ready', 'Product Pricelist siap diproses: '.$material->product_code, ($origin === RndMaterialPricingRequest::ORIGIN_WIP ? 'WIP' : 'Bahan dengan sampel disetujui R&D').' masuk antrean Finance.');

            return $request;
        });
    }

    public function activeKey(RndProductEsbMaterial $material): string
    {
        return 'material:'.$material->id;
    }
}
