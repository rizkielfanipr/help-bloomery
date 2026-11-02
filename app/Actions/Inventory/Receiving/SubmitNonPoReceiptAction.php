<?php

namespace App\Actions\Inventory\Receiving;

use App\Enums\NonPoReceiptStatus;
use App\Models\NonPoReceipt;
use App\Models\NonPoReceiptItem;
use App\Models\User;
use App\Services\EsbBranchMappingResolver;
use App\Services\EsbGoodsReceiptService;
use App\Services\EsbItemJournalService;
use App\Services\EsbService;
use App\Services\InboundGoodsReceiptQcService;
use App\Services\NonPoReceiptNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Validates what is stored rather than what the browser holds, then routes the receipt by its QC
 * split (docs/products/inventory/non-po-receiving/prd.md §11). ESB is only read, before the
 * transaction: no Goods Receipt is created here. A repeated submit changes nothing.
 */
class SubmitNonPoReceiptAction
{
    public function __construct(
        private readonly TransitionNonPoReceiptAction $transition,
        private readonly InboundGoodsReceiptQcService $qc,
        private readonly EsbItemJournalService $branches,
        private readonly EsbGoodsReceiptService $goodsReceipts,
        private readonly EsbService $products,
        private readonly EsbBranchMappingResolver $branchMappings,
        private readonly NonPoReceiptNotifier $notifier,
    ) {}

    public function execute(User $actor, NonPoReceipt $receipt): NonPoReceipt
    {
        $receipt->refresh()->load('items.batches');
        if (! $receipt->status->canBeEditedByInventory()) {
            return $receipt;
        }

        Gate::forUser($actor)->authorize('submit', $receipt);
        $this->assertComplete($receipt);
        $context = $this->verifiedContext($receipt);

        $transitioned = false;
        $receipt = DB::transaction(function () use ($actor, $receipt, $context, &$transitioned): NonPoReceipt {
            $locked = NonPoReceipt::query()->whereKey($receipt->getKey())->lockForUpdate()->firstOrFail();
            if (! $locked->status->canBeEditedByInventory()) {
                return $locked;
            }

            $locked->load('items.batches');
            $this->applyVerifiedProducts($locked, $context['products']);
            $this->assertQc($locked);

            $accepted = (float) $locked->items->sum('accepted_qty');
            $hold = (float) $locked->items->sum('hold_qty');
            $rejected = (float) $locked->items->sum('rejected_qty');
            $target = match (true) {
                $accepted > 0 => NonPoReceiptStatus::AwaitingPurchasing,
                $rejected > 0 && $hold <= 0 => NonPoReceiptStatus::QcRejected,
                default => NonPoReceiptStatus::QcHold,
            };

            foreach ($locked->items as $item) {
                $item->update([
                    'disposition' => $this->qc->disposition((float) $item->accepted_qty, (float) $item->hold_qty, (float) $item->rejected_qty),
                    'qc_inspected_by' => $actor->id, 'qc_inspected_at' => now(),
                ]);
            }

            $this->transition->execute($locked, $target, $actor, null, [
                'receipt_number' => $locked->receipt_number ?? 'NPR-'.now()->format('Ymd').'-'.str_pad((string) $locked->id, 6, '0', STR_PAD_LEFT),
                'esb_branch_code' => $context['branch']['branchCode'] ?? $locked->esb_branch_code,
                'branch_name' => $context['branch']['branchName'] ?? $locked->branch_name,
                'location_name' => $context['location']['locationName'] ?? $locked->location_name,
                'local_branch_id' => $context['localBranchId'],
                'submitted_by' => $actor->id, 'submitted_at' => now(),
                'payload_hash' => hash('sha256', json_encode($locked->items->map->only(['product_detail_id', 'uom_name', 'physical_qty', 'accepted_qty', 'hold_qty', 'rejected_qty'])->all(), JSON_THROW_ON_ERROR)),
            ]);
            $transitioned = true;

            return $locked;
        });

        if ($transitioned && $receipt->status === NonPoReceiptStatus::AwaitingPurchasing) {
            $this->notifier->submittedToPurchasing($receipt);
        }

        return $receipt;
    }

    private function assertComplete(NonPoReceipt $receipt): void
    {
        $errors = [];
        foreach ([
            'supplierText' => [$receipt->supplier_text, 'Supplier wajib diisi.'],
            'reason' => [$receipt->reason, 'Alasan penerimaan tanpa PO wajib diisi.'],
            'branchId' => [$receipt->esb_branch_id, 'Cabang ESB wajib dipilih.'],
            'locationId' => [$receipt->location_id, 'Lokasi ESB wajib dipilih.'],
            'documentNumber' => [$receipt->document_number, 'Nomor dokumen wajib diisi.'],
            'documentDate' => [$receipt->document_date, 'Tanggal dokumen wajib diisi.'],
            'documentPhotos' => [$receipt->document_photos, 'Foto dokumen wajib dilampirkan.'],
            'goodsPhotos' => [$receipt->goods_photos, 'Foto barang wajib dilampirkan.'],
        ] as $field => [$value, $message]) {
            if (blank($value)) {
                $errors[$field] = $message;
            }
        }

        if ($receipt->items->isEmpty()) {
            $errors['items'] = 'Pilih minimal satu produk dari Master Product.';
        }

        foreach ($receipt->items->values() as $index => $item) {
            if ((float) $item->physical_qty <= 0) {
                $errors["items.{$index}.physicalQty"] = 'Qty fisik harus lebih dari 0.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Branch, location, and every product are looked up again so a stale or tampered draft cannot
     * reach Purchasing.
     *
     * @return array{branch: array<string, mixed>, location: array<string, mixed>, products: array<int, array<string, mixed>>, localBranchId: ?int}
     */
    private function verifiedContext(NonPoReceipt $receipt): array
    {
        $branch = collect($this->branches->branches($receipt->company_code))->firstWhere('branchID', (int) $receipt->esb_branch_id);
        if (! is_array($branch)) {
            throw ValidationException::withMessages(['branchId' => 'Cabang tidak ditemukan pada ESB '.$receipt->company_code.'.']);
        }

        $location = collect($this->goodsReceipts->locations($receipt->company_code, (int) $receipt->esb_branch_id))->firstWhere('locationID', (int) $receipt->location_id);
        if (! is_array($location)) {
            throw ValidationException::withMessages(['locationId' => 'Lokasi tidak valid untuk cabang ini.']);
        }

        return [
            'branch' => $branch,
            'location' => $location,
            'products' => $this->products->getActiveProductDetailsByCodes($receipt->items->pluck('product_code')->all()),
            'localBranchId' => $this->branchMappings->lookup($receipt->company_code, (int) $receipt->esb_branch_id, $branch['branchCode'] ?? null)?->branch_id,
        ];
    }

    /** @param array<int, array<string, mixed>> $products */
    private function applyVerifiedProducts(NonPoReceipt $receipt, array $products): void
    {
        $errors = [];
        foreach ($receipt->items->values() as $index => $item) {
            $product = $products[(int) $item->product_detail_id] ?? null;
            if ($product === null || NonPoReceiptItem::normalizeUom($product['unit'] ?? '') !== NonPoReceiptItem::normalizeUom($item->uom_name)) {
                $errors["items.{$index}.productDetailID"] = "{$item->product_name} tidak cocok dengan Master Product aktif.";

                continue;
            }
            $item->update([
                'product_id' => $product['productID'], 'product_code' => $product['productCode'],
                'product_name' => $product['productName'], 'uom_name' => $product['unit'],
            ]);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function assertQc(NonPoReceipt $receipt): void
    {
        $documentsPass = $this->qc->documentsPass([
            'poDocumentMatch' => true, 'documentMatch' => $receipt->document_match, 'priceMatch' => true,
        ]);
        $errors = [];
        foreach ($receipt->items->values() as $index => $item) {
            $receivingItem = $item->toReceivingItem();
            $assessed = array_replace($receivingItem, $this->qc->assessItem($receivingItem, $receipt->receipt_date->toDateString()));
            foreach ($this->qc->itemViolations($assessed, $documentsPass) as $field => $message) {
                $errors["items.{$index}.{$field}"] = $message;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
