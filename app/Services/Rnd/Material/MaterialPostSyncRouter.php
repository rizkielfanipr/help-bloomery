<?php

namespace App\Services\Rnd\Material;

use App\Enums\MaterialSourcingStatus;
use App\Models\RndProductEsbMaterial;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * After a verified sync (§10.1 step 11, §10.2 step 6): an ordinary material gets exactly one
 * Sourcing Request; a WIP gets `not_required` sourcing and a Pricing Request. Never both, and a
 * WIP never receives supplier/sample records.
 */
class MaterialPostSyncRouter
{
    public function __construct(
        private readonly MaterialAuditLogger $audit,
        private readonly MaterialWorkflowNotifier $notifier,
        private readonly MaterialPricingEligibility $pricing,
        private readonly MaterialWorkflowCompletion $completion,
    ) {}

    public function route(RndProductEsbMaterial $material, ?User $actor): void
    {
        $material->refresh();
        if (! $material->isInWorkflow() || ! $material->isVerifiedSynced()) {
            return;
        }

        if ($material->requiresSourcing()) {
            $this->requestSourcing($material, $actor);
        } else {
            DB::transaction(function () use ($material, $actor): void {
                $updated = RndProductEsbMaterial::query()->whereKey($material->id)
                    ->where(fn ($query) => $query->whereNull('sourcing_status')->orWhere('sourcing_status', '!=', MaterialSourcingStatus::NotRequired->value))
                    ->update(['sourcing_status' => MaterialSourcingStatus::NotRequired->value]);
                if ($updated === 1) {
                    $this->audit->event($material, 'sourcing_not_required', $actor, to: MaterialSourcingStatus::NotRequired->value, metadata: ['reason' => 'wip']);
                }
            });
        }

        $this->pricing->ensure($material->refresh(), $actor);
        $this->completion->evaluate($material->refresh());
    }

    private function requestSourcing(RndProductEsbMaterial $material, ?User $actor): void
    {
        DB::transaction(function () use ($material, $actor): void {
            $created = RndProductEsbMaterial::query()->whereKey($material->id)
                ->whereNull('sourcing_requested_at')
                ->whereIn('sourcing_status', [MaterialSourcingStatus::NotStarted->value])
                ->update(['sourcing_status' => MaterialSourcingStatus::WaitingPurchasing->value, 'sourcing_requested_at' => now()]);

            if ($created === 1) {
                $this->audit->event($material, 'sourcing_requested', $actor, from: MaterialSourcingStatus::NotStarted->value, to: MaterialSourcingStatus::WaitingPurchasing->value);
                $this->notifier->notify($material, 'sourcing_requested', 'Sourcing Request baru: '.$material->product_code, "{$material->product_name} sudah tersinkron ke ESB dan menunggu sourcing Purchasing.");
            }
        });
    }
}
