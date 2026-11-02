<?php

namespace App\Actions\Purchasing\NonPoReceipt;

use App\Actions\Inventory\Receiving\TransitionNonPoReceiptAction;
use App\Enums\NonPoReceiptStatus;
use App\Models\GoodsReceipt;
use App\Models\NonPoReceipt;
use App\Models\User;
use App\Services\Monitoring\EsbTransactionRecorder;
use App\Services\NonPoReceiptNotifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Manual reconciliation of a failed or unknown Goods Receipt: nothing here runs automatically
 * (docs/products/inventory/non-po-receiving/prd.md §13, §29).
 */
class ResolveNonPoGoodsReceiptAction
{
    public function __construct(
        private readonly TransitionNonPoReceiptAction $transition,
        private readonly ComparePurchaseOrderAction $compare,
        private readonly ProcessNonPoGoodsReceiptAction $process,
        private readonly EsbTransactionRecorder $recorder,
        private readonly NonPoReceiptNotifier $notifier,
    ) {}

    /** The GR was found in ESB: record its number and finish. */
    public function linkExisting(User $actor, NonPoReceipt $receipt, string $goodsReceiptNumber, string $note): NonPoReceipt
    {
        $goodsReceiptNumber = trim($goodsReceiptNumber);
        if ($goodsReceiptNumber === '' || blank($note)) {
            throw ValidationException::withMessages(['goods_receipt_number' => 'Nomor GR dan catatan wajib diisi.']);
        }

        try {
            $updated = DB::transaction(function () use ($actor, $receipt, $goodsReceiptNumber, $note): NonPoReceipt {
                $locked = $this->lockForResolution($actor, $receipt);
                $goodsReceipt = $locked->goodsReceipt;
                $partial = $goodsReceipt->items()->where(fn ($query) => $query->where('hold_qty', '>', 0)->orWhere('rejected_qty', '>', 0))->exists();
                $goodsReceipt->update([
                    'status' => $partial ? GoodsReceipt::STATUS_PARTIAL_SUCCEEDED : GoodsReceipt::STATUS_SUCCEEDED,
                    'esb_goods_receipt_number' => $goodsReceiptNumber, 'sync_error' => null, 'synced_at' => now(),
                ]);

                return $this->transition->execute($locked, NonPoReceiptStatus::Completed, $actor, "GR {$goodsReceiptNumber} ditautkan: {$note}");
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['goods_receipt_number' => "Nomor GR {$goodsReceiptNumber} sudah tercatat pada penerimaan lain."]);
        }

        if ($monitoring = $this->recorder->latestParentFor($updated->goodsReceipt)) {
            $this->recorder->reconcile($monitoring, 'confirmed_applied', $note, $actor);
        }

        $this->notifier->goodsReceiptResult($updated);

        return $updated;
    }

    /** The GR was confirmed absent from ESB: check the PO again, then re-send the same payload. */
    public function resend(User $actor, NonPoReceipt $receipt, string $reason): NonPoReceipt
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Alasan kirim ulang wajib diisi.']);
        }

        $receipt->refresh();
        if (! $receipt->status->needsGoodsReceiptResolution()) {
            return $receipt;
        }

        Gate::forUser($actor)->authorize('resolveGoodsReceipt', $receipt);

        $comparison = $this->compare->execute($receipt, (string) $receipt->po_number);
        if ($comparison['blockers'] !== []) {
            throw ValidationException::withMessages(['reason' => $comparison['blockers']]);
        }

        return $this->process->resend($actor, $receipt, $reason);
    }

    private function lockForResolution(User $actor, NonPoReceipt $receipt): NonPoReceipt
    {
        $locked = NonPoReceipt::query()->whereKey($receipt->getKey())->lockForUpdate()->firstOrFail();
        if (! $locked->status->needsGoodsReceiptResolution() || $locked->goodsReceipt === null) {
            throw ValidationException::withMessages(['status' => 'Penerimaan ini tidak memerlukan rekonsiliasi.']);
        }

        Gate::forUser($actor)->authorize('resolveGoodsReceipt', $locked);

        return $locked;
    }
}
