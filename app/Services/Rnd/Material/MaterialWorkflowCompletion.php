<?php

namespace App\Services\Rnd\Material;

use App\Enums\MaterialPricingStatus;
use App\Enums\MaterialSourcingStatus;
use App\Models\RndProductEsbMaterial;
use Illuminate\Support\Facades\DB;

/**
 * `Completed` is derived, never chosen (§11.5):
 *
 * - ordinary material: verified sync AND sourcing `approved_by_rnd` AND pricing `priced`;
 * - WIP: verified sync AND sourcing `not_required` AND pricing `priced`.
 *
 * Evaluation is idempotent; the completion timestamp and notification happen once.
 */
class MaterialWorkflowCompletion
{
    public function __construct(private readonly MaterialAuditLogger $audit, private readonly MaterialWorkflowNotifier $notifier) {}

    public function guardsMet(RndProductEsbMaterial $material): bool
    {
        if (! $material->isInWorkflow() || ! $material->isVerifiedSynced() || $material->pricing_status !== MaterialPricingStatus::Priced) {
            return false;
        }

        return $material->isWip()
            ? $material->sourcing_status === MaterialSourcingStatus::NotRequired
            : $material->sourcing_status === MaterialSourcingStatus::ApprovedByRnd;
    }

    public function evaluate(RndProductEsbMaterial $material): bool
    {
        if (! $this->guardsMet($material)) {
            return false;
        }

        return DB::transaction(function () use ($material): bool {
            $completed = RndProductEsbMaterial::query()->whereKey($material->id)->whereNull('workflow_completed_at')
                ->update(['workflow_completed_at' => now()]);
            if ($completed !== 1) {
                return false;
            }

            $this->audit->event($material, 'workflow_completed', null, to: 'completed', metadata: ['actor' => 'system', 'section' => $material->materialSection()]);
            $this->notifier->notify($material, 'completed', 'Workflow Master Bahan selesai: '.$material->product_code, 'ESB, sampel/sourcing, dan Pricelist sudah terverifikasi.', includeCreator: true);

            return true;
        });
    }
}
