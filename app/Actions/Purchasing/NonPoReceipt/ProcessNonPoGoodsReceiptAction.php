<?php

namespace App\Actions\Purchasing\NonPoReceipt;

use App\Actions\Inventory\Receiving\CreateGoodsReceiptAction;
use App\Actions\Inventory\Receiving\TransitionNonPoReceiptAction;
use App\Enums\NonPoReceiptStatus;
use App\Models\GoodsReceipt;
use App\Models\NonPoReceipt;
use App\Models\NonPoReceiptItem;
use App\Models\User;
use App\Services\InboundGoodsReceiptQcService;
use App\Services\NonPoReceiptNotifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns a PO-verified receipt into one Goods Receipt through the shared Action. The status change to
 * `gr_processing` under a row lock is the gate: only the request that performs it may call ESB, and
 * the HTTP call itself runs outside every transaction.
 */
class ProcessNonPoGoodsReceiptAction
{
    private const MONITORING_ACTION = 'submit_non_po';

    private const IDEMPOTENCY_PREFIX = 'non-po-goods-receipt';

    public function __construct(
        private readonly CreateGoodsReceiptAction $goodsReceipts,
        private readonly TransitionNonPoReceiptAction $transition,
        private readonly InboundGoodsReceiptQcService $qc,
        private readonly NonPoReceiptNotifier $notifier,
    ) {}

    public function execute(User $actor, NonPoReceipt $receipt): NonPoReceipt
    {
        $goodsReceipt = null;
        $locked = DB::transaction(function () use ($actor, $receipt, &$goodsReceipt): NonPoReceipt {
            $locked = NonPoReceipt::query()->whereKey($receipt->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== NonPoReceiptStatus::PoLinked) {
                return $locked;
            }

            $locked->load('items.batches');
            $goodsReceipt = $this->goodsReceipts->record($this->header($locked), $this->items($locked), $actor->id);

            return $this->transition->execute($locked, NonPoReceiptStatus::GrProcessing, $actor, null, ['goods_receipt_id' => $goodsReceipt->id]);
        });

        return $goodsReceipt === null ? $locked : $this->dispatch($actor, $locked, $goodsReceipt, false);
    }

    /** An explicit, audited re-send of the stored payload; never called automatically. */
    public function resend(User $actor, NonPoReceipt $receipt, string $reason): NonPoReceipt
    {
        $goodsReceipt = null;
        $locked = DB::transaction(function () use ($actor, $receipt, $reason, &$goodsReceipt): NonPoReceipt {
            $locked = NonPoReceipt::query()->whereKey($receipt->getKey())->lockForUpdate()->firstOrFail();
            if (! $locked->status->needsGoodsReceiptResolution() || $locked->goodsReceipt === null) {
                return $locked;
            }

            $goodsReceipt = $locked->goodsReceipt;
            $goodsReceipt->update(['status' => GoodsReceipt::STATUS_PROCESSING, 'sync_error' => null, 'attempted_at' => now()]);

            return $this->transition->execute($locked, NonPoReceiptStatus::GrProcessing, $actor, $reason);
        });

        return $goodsReceipt === null ? $locked : $this->dispatch($actor, $locked, $goodsReceipt, true);
    }

    private function dispatch(User $actor, NonPoReceipt $receipt, GoodsReceipt $goodsReceipt, bool $resume): NonPoReceipt
    {
        $this->goodsReceipts->send($goodsReceipt, self::MONITORING_ACTION, self::IDEMPOTENCY_PREFIX, $resume);

        $updated = DB::transaction(function () use ($actor, $receipt, $goodsReceipt): NonPoReceipt {
            $locked = NonPoReceipt::query()->whereKey($receipt->getKey())->lockForUpdate()->firstOrFail();

            return $this->transition->execute($locked, match ($goodsReceipt->status) {
                GoodsReceipt::STATUS_SUCCEEDED, GoodsReceipt::STATUS_PARTIAL_SUCCEEDED => NonPoReceiptStatus::Completed,
                GoodsReceipt::STATUS_UNKNOWN => NonPoReceiptStatus::GrUnknown,
                default => NonPoReceiptStatus::GrFailed,
            }, $actor, $goodsReceipt->sync_error);
        });

        $this->notifier->goodsReceiptResult($updated);

        return $updated;
    }

    /** @return array<string, mixed> */
    private function header(NonPoReceipt $receipt): array
    {
        return [
            'source' => GoodsReceipt::SOURCE_NON_PO,
            'submissionKey' => $receipt->submission_key, 'companyCode' => $receipt->company_code, 'purchaseNumber' => $receipt->po_number,
            'purchaseDate' => $receipt->po_date, 'goodsReceiptDate' => $receipt->receipt_date->toDateString(),
            'esbBranchId' => (int) $receipt->esb_branch_id, 'localBranchId' => $receipt->local_branch_id, 'branchName' => $receipt->branch_name,
            'supplierId' => $receipt->po_supplier_id, 'supplierName' => $receipt->po_supplier_name,
            'location' => ['locationID' => (int) $receipt->location_id, 'locationName' => (string) $receipt->location_name],
            'documentType' => $receipt->document_type, 'documentNumber' => (string) $receipt->document_number, 'documentDate' => $receipt->document_date,
            'documentPhotos' => $receipt->document_photos ?? [], 'goodsPhotos' => $receipt->goods_photos ?? [],
            'poDocumentMatch' => true, 'documentMatch' => $receipt->document_match, 'priceMatch' => true,
            'documentNotes' => $receipt->document_notes, 'additionalInfo' => $receipt->additional_info,
        ];
    }

    /**
     * QC is reused from Inventory's inspection: the PO was written after the goods arrived, so the
     * quantity is not re-graded against it. Accepted against outstanding was enforced at verification.
     */
    private function items(NonPoReceipt $receipt): Collection
    {
        return $receipt->items->map(function (NonPoReceiptItem $record) use ($receipt): array {
            $item = $record->toReceivingItem();

            return array_replace($item, $this->qc->assessItem(array_replace($item, ['outstandingQty' => null]), $receipt->receipt_date->toDateString()), [
                'outstandingQty' => (float) $item['outstandingQty'],
                'qcInspectedBy' => $record->qc_inspected_by,
                'qcInspectedAt' => $record->qc_inspected_at,
            ]);
        });
    }
}
