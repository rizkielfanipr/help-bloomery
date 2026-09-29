<?php

namespace App\Actions\Rnd\Bom;

use App\Enums\RndBomChangeLogStatus;
use App\Models\RndBomChangeLog;
use App\Models\User;
use App\Services\EsbBillOfMaterialService;
use App\Services\Rnd\Bom\BomChangeComparator;
use Illuminate\Validation\ValidationException;

/**
 * Resolves a `needs_reconciliation` change log by re-fetching the BOM's current ESB state and
 * comparing it against what was requested at mutation time (docs/rnd-bom-adjustment-prd.md §14).
 * If ESB's current state matches the requested snapshot, the timeout/connection failure clearly
 * did not prevent the mutation from applying, so the record is promoted to `success`; otherwise
 * it is marked `failed`. The mutation itself is never retried — reconciliation only observes.
 */
class ReconcileBomAdjustmentAction
{
    public function __construct(
        private readonly EsbBillOfMaterialService $bomService,
        private readonly BomChangeComparator $comparator,
    ) {}

    public function execute(RndBomChangeLog $changeLog, User $reconciler): RndBomChangeLog
    {
        if ($changeLog->status !== RndBomChangeLogStatus::NeedsReconciliation) {
            throw ValidationException::withMessages([
                'status' => 'Hanya record berstatus Needs Reconciliation yang dapat direkonsiliasi.',
            ]);
        }

        $latest = $this->bomService->getBillOfMaterial($changeLog->esb_bom_id);
        $expected = $this->expectedAfterState($changeLog);

        $applied = $expected !== null && ! $this->comparator->compare($expected, $latest)['has_changes'];

        $changeLog->update([
            'status' => $applied ? RndBomChangeLogStatus::Success : RndBomChangeLogStatus::Failed,
            'after_snapshot' => $latest,
            'esb_edited_at_after' => $latest['editedDate'] ?? null,
            'reconciled_by' => $reconciler->id,
            'reconciled_at' => now(),
        ]);

        return $changeLog->fresh();
    }

    /**
     * The outgoing payload (`requested_snapshot`) only carries fields the app can edit and is
     * shaped like a PUT body, not a GET response — it omits fields such as `uomName` that ESB
     * derives on its own. Comparing it against the response shape directly would report false
     * "unit changed" / "status changed" positives. Instead, project the requested component and
     * result-product changes onto the pre-mutation snapshot to get the state a successful
     * mutation should have produced, then diff that against the live ESB state.
     *
     * @return array<string, mixed>|null
     */
    private function expectedAfterState(RndBomChangeLog $changeLog): ?array
    {
        $before = $changeLog->before_snapshot;
        $requested = $changeLog->requested_snapshot;

        if (! is_array($before) || ! is_array($requested)) {
            return null;
        }

        $expected = $before;

        if (array_key_exists('productDetailID', $requested)) {
            $expected['productDetailID'] = $requested['productDetailID'];
        }

        $expected['bomDetails'] = $requested['bomDetails'] ?? ($expected['bomDetails'] ?? []);

        return $expected;
    }
}
