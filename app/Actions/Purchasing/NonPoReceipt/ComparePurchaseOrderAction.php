<?php

namespace App\Actions\Purchasing\NonPoReceipt;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\NonPoReceipt;
use App\Models\NonPoReceiptItem;
use App\Services\EsbGoodsReceiptService;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Read-only comparison of a receipt with the current PO in ESB
 * (docs/products/inventory/non-po-receiving/prd.md §11, §15). It always fetches the PO itself, so
 * nothing the browser holds can vouch for a PO. Hold/Rejected-only items need no PO line.
 */
class ComparePurchaseOrderAction
{
    private const COMPANY_CODE = 'BLSS';

    public function __construct(private readonly EsbGoodsReceiptService $goodsReceipts) {}

    /**
     * @return array{poNumber: string, order: array<string, mixed>|null, statusName: ?string, branchName: ?string, supplierName: ?string, localSupplier: ?string, supplierMismatch: bool, blockers: list<string>, items: list<array<string, mixed>>}
     */
    public function execute(NonPoReceipt $receipt, string $poNumber): array
    {
        $poNumber = trim($poNumber);
        $receipt->loadMissing('items');
        $result = [
            'poNumber' => $poNumber, 'order' => null, 'statusName' => null, 'branchName' => null, 'supplierName' => null,
            'localSupplier' => $receipt->supplier_text, 'supplierMismatch' => false, 'blockers' => [], 'items' => [],
        ];

        if ($receipt->company_code !== self::COMPANY_CODE) {
            $result['blockers'][] = 'Company Code penerimaan harus '.self::COMPANY_CODE.'.';

            return $result;
        }

        try {
            $order = $this->goodsReceipts->purchaseOrder($receipt->company_code, $poNumber);
        } catch (Throwable $exception) {
            report($exception);
            $result['blockers'][] = "PO {$poNumber} tidak dapat diambil dari ESB {$receipt->company_code}: {$exception->getMessage()}";

            return $result;
        }

        $result['order'] = $order;
        $result['statusName'] = data_get($order, 'statusName');
        $result['branchName'] = data_get($order, 'branchName');
        $result['supplierName'] = data_get($order, 'supplierName');

        $orderCompany = data_get($order, 'companyCode');
        if (filled($orderCompany) && $orderCompany !== $receipt->company_code) {
            $result['blockers'][] = "PO milik company {$orderCompany}, bukan {$receipt->company_code}.";
        }

        if (! in_array((int) data_get($order, 'statusID'), [EsbGoodsReceiptService::PURCHASE_ORDER_STATUS_AUTHORIZED, EsbGoodsReceiptService::PURCHASE_ORDER_STATUS_RECEIVING], true)) {
            $result['blockers'][] = 'Status PO harus Authorized atau Receiving.';
        }

        if ((int) data_get($order, 'branchID') !== (int) $receipt->esb_branch_id) {
            $result['blockers'][] = 'Cabang PO berbeda dengan cabang penerimaan.';
        }

        if (NonPoReceipt::query()->where('company_code', $receipt->company_code)->where('po_number', $poNumber)->whereKeyNot($receipt->getKey())->exists()) {
            $result['blockers'][] = "PO {$poNumber} sudah tertaut ke penerimaan lain.";
        }

        $acceptedItems = $receipt->items->filter(fn (NonPoReceiptItem $item): bool => (float) $item->accepted_qty > 0);
        if ($acceptedItems->isEmpty()) {
            $result['blockers'][] = 'Tidak ada qty Accepted yang dapat diproses.';
        }

        $received = $this->receivedQuantities($receipt, $poNumber);
        $lines = collect(data_get($order, 'purchaseDetails', data_get($order, 'purchaseOrderDetails', [])))
            ->groupBy(fn (array $line): string => $this->identity((int) ($line['productDetailID'] ?? 0), $line['uomName'] ?? ''));

        foreach ($receipt->items as $item) {
            $result['items'][] = $this->compareItem($item, $lines->get($this->identity((int) $item->product_detail_id, $item->uom_name)), $received, $result['blockers']);
        }

        $result['supplierMismatch'] = $this->normalizeText($result['localSupplier']) !== $this->normalizeText($result['supplierName']);

        return $result;
    }

    /**
     * @param  Collection<int, array<string, mixed>>|null  $lines
     * @param  array<int, float>  $received
     * @param  list<string>  $blockers
     * @return array<string, mixed>
     */
    private function compareItem(NonPoReceiptItem $item, ?Collection $lines, array $received, array &$blockers): array
    {
        $accepted = (float) $item->accepted_qty;
        $comparison = [
            'itemId' => $item->id, 'productCode' => $item->product_code, 'productName' => $item->product_name, 'productDetailId' => (int) $item->product_detail_id,
            'uomName' => $item->uom_name, 'acceptedQty' => $accepted, 'matched' => false, 'poProductName' => null, 'poUomId' => null, 'poUomName' => null,
            'purchaseDetailId' => null, 'orderedQty' => null, 'outstandingQty' => null, 'deviationValue' => null, 'message' => null,
        ];

        if ($lines === null || $lines->isEmpty()) {
            $comparison['message'] = 'Product Detail ID + UOM tidak ada pada PO.';
            if ($accepted > 0) {
                $blockers[] = "{$item->product_name} ({$item->uom_name}) tidak ada pada PO dengan Product Detail ID dan UOM yang sama.";
            }

            return $comparison;
        }

        $line = $lines->first();
        $ordered = (float) $lines->sum(fn (array $candidate): float => (float) ($candidate['qty'] ?? 0));
        $outstanding = max(0, $ordered - ($received[(int) $item->product_detail_id] ?? 0));
        $comparison = array_replace($comparison, [
            'matched' => true, 'poProductName' => $line['productName'] ?? null, 'poUomId' => $line['uomID'] ?? null, 'poUomName' => $line['uomName'] ?? null,
            'purchaseDetailId' => $line['ID'] ?? null, 'orderedQty' => $ordered, 'outstandingQty' => $outstanding, 'deviationValue' => (float) ($line['deviationVal'] ?? 0),
        ]);

        if ($accepted > $outstanding) {
            $comparison['message'] = 'Qty Accepted melebihi outstanding PO.';
            $blockers[] = "Qty Accepted {$item->product_name} melebihi outstanding PO.";
        }

        return $comparison;
    }

    /**
     * Unlike the normal Receiving list, in-flight and unknown receipts count too: their stock may
     * already exist in ESB. This receipt's own failed attempt is not double counted.
     *
     * @return array<int, float>
     */
    private function receivedQuantities(NonPoReceipt $receipt, string $poNumber): array
    {
        return GoodsReceiptItem::query()
            ->whereHas('goodsReceipt', fn ($query) => $query->where('company_code', $receipt->company_code)
                ->where('reference_number', $poNumber)
                ->whereIn('status', [GoodsReceipt::STATUS_SUCCEEDED, GoodsReceipt::STATUS_PARTIAL_SUCCEEDED, GoodsReceipt::STATUS_PROCESSING, GoodsReceipt::STATUS_UNKNOWN])
                ->when($receipt->goods_receipt_id, fn ($query, int $id) => $query->whereKeyNot($id)))
            ->get(['product_detail_id', 'accepted_qty'])
            ->groupBy('product_detail_id')
            ->map(fn (Collection $items): float => (float) $items->sum('accepted_qty'))
            ->all();
    }

    private function identity(int $productDetailId, ?string $uom): string
    {
        return $productDetailId.'|'.NonPoReceiptItem::normalizeUom($uom);
    }

    private function normalizeText(?string $text): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim((string) $text)));
    }
}
