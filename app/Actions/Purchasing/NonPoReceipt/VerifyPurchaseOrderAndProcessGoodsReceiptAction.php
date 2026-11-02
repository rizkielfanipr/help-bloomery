<?php

namespace App\Actions\Purchasing\NonPoReceipt;

use App\Actions\Inventory\Receiving\TransitionNonPoReceiptAction;
use App\Enums\NonPoReceiptStatus;
use App\Models\NonPoReceipt;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * "Verifikasi PO & Proses GR": re-fetches the PO, links it, then creates the Goods Receipt
 * (docs/products/inventory/non-po-receiving/prd.md §10). A duplicate click finds the record past
 * its status and returns it without another request or receipt.
 */
class VerifyPurchaseOrderAndProcessGoodsReceiptAction
{
    public function __construct(
        private readonly ComparePurchaseOrderAction $compare,
        private readonly TransitionNonPoReceiptAction $transition,
        private readonly ProcessNonPoGoodsReceiptAction $process,
    ) {}

    public function execute(User $actor, NonPoReceipt $receipt, string $poNumber, bool $supplierMismatchConfirmed = false): NonPoReceipt
    {
        $receipt->refresh();
        $poNumber = trim($poNumber);

        if (! in_array($receipt->status, [NonPoReceiptStatus::PoInProgress, NonPoReceiptStatus::PoLinked], true)) {
            if ($receipt->status === NonPoReceiptStatus::AwaitingPurchasing) {
                throw ValidationException::withMessages(['po_number' => 'Mulai proses PO terlebih dahulu.']);
            }

            return $receipt;
        }

        Gate::forUser($actor)->authorize('verifyPurchaseOrder', $receipt);

        if ($poNumber === '') {
            throw ValidationException::withMessages(['po_number' => 'Nomor PO wajib diisi.']);
        }

        if ($receipt->status === NonPoReceiptStatus::PoLinked && $receipt->po_number !== $poNumber) {
            throw ValidationException::withMessages(['po_number' => "Penerimaan ini sudah tertaut ke PO {$receipt->po_number}."]);
        }

        $comparison = $this->compare->execute($receipt, $poNumber);

        if ($comparison['blockers'] !== []) {
            $this->releaseLinkedPurchaseOrder($actor, $receipt, $comparison['blockers']);

            throw ValidationException::withMessages(['po_number' => $comparison['blockers']]);
        }

        if ($comparison['supplierMismatch'] && ! $supplierMismatchConfirmed) {
            throw ValidationException::withMessages(['supplier_mismatch_confirmed' => "Supplier PO ({$comparison['supplierName']}) berbeda dengan catatan Inventory ({$comparison['localSupplier']}). Konfirmasi untuk melanjutkan."]);
        }

        $linked = $this->link($actor, $receipt, $comparison);

        return $linked->status === NonPoReceiptStatus::PoLinked
            ? $this->process->execute($actor, $linked)
            : $linked;
    }

    /** @param array<string, mixed> $comparison */
    private function link(User $actor, NonPoReceipt $receipt, array $comparison): NonPoReceipt
    {
        try {
            return DB::transaction(function () use ($actor, $receipt, $comparison): NonPoReceipt {
                $locked = NonPoReceipt::query()->whereKey($receipt->getKey())->lockForUpdate()->firstOrFail();
                if ($locked->status !== NonPoReceiptStatus::PoInProgress) {
                    return $locked;
                }

                $locked->load('items');
                foreach ($comparison['items'] as $line) {
                    $locked->items->firstWhere('id', $line['itemId'])?->update([
                        'purchase_detail_id' => $line['purchaseDetailId'], 'po_uom_id' => $line['poUomId'], 'po_uom_name' => $line['poUomName'],
                        'po_ordered_qty' => $line['orderedQty'], 'po_outstanding_qty' => $line['outstandingQty'], 'po_deviation_value' => $line['deviationValue'],
                    ]);
                }

                $order = $comparison['order'];
                $confirmed = $comparison['supplierMismatch'];

                return $this->transition->execute(
                    $locked,
                    NonPoReceiptStatus::PoLinked,
                    $actor,
                    $confirmed ? "Supplier berbeda dikonfirmasi: {$comparison['localSupplier']} → {$comparison['supplierName']}" : null,
                    [
                        'po_number' => $comparison['poNumber'], 'po_date' => data_get($order, 'purchaseDate'),
                        'po_supplier_id' => data_get($order, 'supplierID'), 'po_supplier_name' => data_get($order, 'supplierName'),
                        'po_snapshot' => $order, 'po_linked_by' => $actor->id, 'po_linked_at' => now(),
                        'supplier_mismatch_confirmed_by' => $confirmed ? $actor->id : null,
                        'supplier_mismatch_confirmed_at' => $confirmed ? now() : null,
                    ],
                );
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['po_number' => "PO {$comparison['poNumber']} sudah tertaut ke penerimaan lain."]);
        }
    }

    /**
     * A link made earlier that no longer verifies goes back to Purchasing instead of staying stuck
     * (prd.md §29). Nothing was sent to ESB for it.
     *
     * @param  list<string>  $blockers
     */
    private function releaseLinkedPurchaseOrder(User $actor, NonPoReceipt $receipt, array $blockers): void
    {
        if ($receipt->status !== NonPoReceiptStatus::PoLinked) {
            return;
        }

        DB::transaction(function () use ($actor, $receipt, $blockers): void {
            $locked = NonPoReceipt::query()->whereKey($receipt->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status === NonPoReceiptStatus::PoLinked) {
                $this->transition->execute($locked, NonPoReceiptStatus::PoInProgress, $actor, implode(' ', $blockers), ['po_number' => null, 'po_linked_by' => null, 'po_linked_at' => null]);
            }
        });
    }
}
