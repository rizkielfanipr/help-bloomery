<?php

namespace App\Actions\Purchasing\NonPoReceipt;

use App\Actions\Inventory\Receiving\TransitionNonPoReceiptAction;
use App\Enums\NonPoReceiptStatus;
use App\Models\GoodsReceipt;
use App\Models\NonPoReceipt;
use App\Models\User;
use App\Services\Monitoring\EsbTransactionRecorder;
use App\Services\NonPoReceiptNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Cancelling never deletes anything. After a failed or unknown Goods Receipt the reason is the
 * audited statement that no GR exists in ESB, so the local GR closes as failed, stops counting
 * against the PO outstanding, and the PO becomes linkable again.
 */
class CancelNonPoReceiptAction
{
    public function __construct(
        private readonly TransitionNonPoReceiptAction $transition,
        private readonly EsbTransactionRecorder $recorder,
        private readonly NonPoReceiptNotifier $notifier,
    ) {}

    public function execute(User $actor, NonPoReceipt $receipt, string $reason): NonPoReceipt
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Alasan pembatalan wajib diisi.']);
        }

        $goodsReceiptToReconcile = null;
        $updated = DB::transaction(function () use ($actor, $receipt, $reason, &$goodsReceiptToReconcile): NonPoReceipt {
            $locked = NonPoReceipt::query()->whereKey($receipt->getKey())->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('cancel', $locked);

            if ($locked->status->needsGoodsReceiptResolution() && $locked->goodsReceipt) {
                if ($locked->status === NonPoReceiptStatus::GrUnknown) {
                    $goodsReceiptToReconcile = $locked->goodsReceipt;
                }
                $locked->goodsReceipt->update(['status' => GoodsReceipt::STATUS_FAILED, 'sync_error' => "Dibatalkan: {$reason}"]);
            }

            return $this->transition->execute($locked, NonPoReceiptStatus::Cancelled, $actor, $reason, ['po_number' => null]);
        });

        if ($goodsReceiptToReconcile !== null && ($monitoring = $this->recorder->latestParentFor($goodsReceiptToReconcile))) {
            $this->recorder->reconcile($monitoring, 'confirmed_not_applied', $reason, $actor);
        }

        $this->notifier->cancelled($updated, $reason);

        return $updated;
    }
}
