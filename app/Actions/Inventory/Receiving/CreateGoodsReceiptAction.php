<?php

namespace App\Actions\Inventory\Receiving;

use App\Models\GoodsReceipt;
use App\Services\EsbGoodsReceiptService;
use App\Services\InboundGoodsReceiptQcService;
use App\Services\Monitoring\EsbTransactionRecorder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The single write boundary for Goods Receipts, shared by Receiving with PO and PO Susulan
 * (docs/products/inventory/non-po-receiving/prd.md §15). record() commits the local QC evidence
 * first; send() then performs the mutation outside any database transaction, so a slow or lost ESB
 * response never holds a row lock or rolls back what was inspected.
 */
class CreateGoodsReceiptAction
{
    public function __construct(
        private readonly EsbGoodsReceiptService $goodsReceipts,
        private readonly InboundGoodsReceiptQcService $qc,
        private readonly EsbTransactionRecorder $recorder,
    ) {}

    /**
     * Persist the receipt once per submission key. A repeated key returns the existing receipt with
     * `wasRecentlyCreated` false and writes nothing.
     *
     * @param  array{submissionKey: string, companyCode: string, purchaseNumber: string, purchaseDate: mixed, goodsReceiptDate: string, esbBranchId: int, localBranchId: ?int, branchName: ?string, supplierId: mixed, supplierName: ?string, location: array{locationID: int, locationName: string}, documentType: string, documentNumber: string, documentDate: mixed, documentPhotos: array<int, string>, goodsPhotos: array<int, string>, poDocumentMatch: bool, documentMatch: bool, priceMatch: bool, documentNotes: ?string, additionalInfo: ?string}  $header
     * @param  Collection<int|string, array<string, mixed>>  $items  QC-assessed items in the Receiving array shape.
     */
    public function record(array $header, Collection $items, int $actorId): GoodsReceipt
    {
        $payload = $this->payload($header, $items);

        return DB::transaction(fn (): GoodsReceipt => $this->persist($header, $payload, $items, $actorId));
    }

    /**
     * Send the stored payload to ESB and store the outcome; only a connection failure is `unknown`.
     * The caller reads the result from the returned receipt's status.
     */
    public function send(GoodsReceipt $receipt, string $sourceAction = 'submit', string $idempotencyPrefix = 'goods-receipt', bool $resumeMonitoring = false): GoodsReceipt
    {
        $context = [
            'company_code' => $receipt->company_code,
            'branch_id' => $receipt->local_branch_id,
            'external_reference' => $receipt->reference_number,
            'idempotency_key' => $idempotencyPrefix.':'.$receipt->submission_key,
        ];
        $mutation = fn () => $this->goodsReceipts->create($receipt->company_code, $receipt->reference_number, $this->storedPayload($receipt));

        try {
            $response = $resumeMonitoring
                ? $this->recorder->resumeOrStart($receipt, 'goods_receipt', $sourceAction, 'create_goods_receipt', $mutation, $context)
                : $this->recorder->transaction('goods_receipt', $sourceAction, 'create_goods_receipt', $mutation, $context + ['local_model' => $receipt]);

            $partial = $receipt->items()->where(fn ($query) => $query->where('hold_qty', '>', 0)->orWhere('rejected_qty', '>', 0))->exists();
            $receipt->update(['status' => $partial ? GoodsReceipt::STATUS_PARTIAL_SUCCEEDED : GoodsReceipt::STATUS_SUCCEEDED,
                'esb_goods_receipt_number' => data_get($response, 'result.goodsReceiptNum'), 'esb_code' => data_get($response, 'response.code'),
                'esb_message' => data_get($response, 'response.message'), 'response_payload' => $response['response'], 'synced_at' => now(), 'sync_error' => null]);
        } catch (Throwable $exception) {
            report($exception);
            $receipt->update(['status' => $this->isConnectionFailure($exception) ? GoodsReceipt::STATUS_UNKNOWN : GoodsReceipt::STATUS_FAILED, 'sync_error' => $exception->getMessage()]);
        }

        return $receipt;
    }

    /**
     * The JSON cast turns whole floats into integers; quantities go back to the type payload() built.
     *
     * @return array<string, mixed>
     */
    private function storedPayload(GoodsReceipt $receipt): array
    {
        $payload = $receipt->request_payload;
        $payload['goodsReceiptDetail'] = collect($payload['goodsReceiptDetail'])->map(fn (array $detail): array => array_replace($detail, [
            'qty' => (float) $detail['qty'],
            'deviationVal' => (float) $detail['deviationVal'],
            'expiredDates' => collect($detail['expiredDates'])->map(fn (array $expiry): array => array_replace($expiry, ['qty' => (float) $expiry['qty']]))->all(),
        ]))->all();

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  Collection<int|string, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function payload(array $header, Collection $items): array
    {
        return ['goodsReceiptDate' => $header['goodsReceiptDate'], 'locationID' => (int) $header['location']['locationID'], 'deliveryNum' => $header['documentNumber'],
            'additionalInfo' => $header['additionalInfo'], 'selectedAssetID' => '',
            // docs/products/inventory/receiving-simplification/prd.md §11/§17 Phase 1: computed automatically in
            // Phase 5 once outstanding-qty-across-the-whole-PO logic exists; false is the safe
            // interim (never auto-closes, matching today's default before the UI checkbox existed).
            'autoClosePO' => false,
            'goodsReceiptDetail' => $items->filter(fn (array $item): bool => (float) $item['acceptedQty'] > 0)->map(fn (array $item): array => [
                'productID' => $item['productID'], 'productDetailID' => $item['productDetailID'], 'qty' => (float) $item['acceptedQty'],
                'deviationVal' => (float) $item['deviationVal'], 'notes' => $item['notes'],
                'expiredDates' => collect($item['batches'])->filter(fn (array $batch): bool => (float) $batch['acceptedQty'] > 0)
                    ->map(fn (array $batch): array => ['expiredDate' => $batch['expiredDate'], 'qty' => (float) $batch['acceptedQty']])->values()->all(),
            ])->values()->all()];
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  array<string, mixed>  $payload
     * @param  Collection<int|string, array<string, mixed>>  $items
     */
    private function persist(array $header, array $payload, Collection $items, int $actorId): GoodsReceipt
    {
        $qc = $this->qc;
        $accepted = (float) $items->sum('acceptedQty');
        $hold = (float) $items->sum('holdQty');
        $rejected = (float) $items->sum('rejectedQty');
        $documentType = $header['documentType'];
        $documentNumber = $header['documentNumber'];
        $documentDate = $header['documentDate'];
        $documentPhotos = $header['documentPhotos'];
        $goodsPhotos = $header['goodsPhotos'];

        $payloadHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $receipt = GoodsReceipt::query()->createOrFirst(['submission_key' => $header['submissionKey']], [
            'company_code' => $header['companyCode'], 'reference_number' => $header['purchaseNumber'], 'purchase_date' => $header['purchaseDate'], 'goods_receipt_date' => $header['goodsReceiptDate'],
            'esb_branch_id' => $header['esbBranchId'], 'local_branch_id' => $header['localBranchId'], 'branch_name' => $header['branchName'],
            'supplier_id' => $header['supplierId'], 'supplier_name' => $header['supplierName'],
            'location_id' => $header['location']['locationID'], 'location_name' => $header['location']['locationName'],
            'document_type' => $documentType, 'document_number' => $documentNumber, 'document_date' => $documentDate,
            'document_photos' => $documentPhotos, 'goods_photos' => $goodsPhotos,
            // Legacy columns stay populated so reports/exports reading them directly keep working
            // during the transition (docs/products/inventory/receiving-simplification/prd.md §15).
            'delivery_number' => $documentType === 'delivery_note' ? $documentNumber : null,
            'delivery_date' => $documentType === 'delivery_note' ? $documentDate : null,
            'invoice_status' => $documentType === 'invoice' ? 'received' : 'not_received',
            'invoice_number' => $documentType === 'invoice' ? $documentNumber : null,
            'invoice_date' => $documentType === 'invoice' ? $documentDate : null,
            'po_document_match' => $header['poDocumentMatch'], 'delivery_document_match' => $header['documentMatch'],
            'invoice_document_match' => $header['documentMatch'], 'price_match' => $header['priceMatch'], 'document_notes' => $header['documentNotes'],
            'document_evidence_photos' => array_merge($documentPhotos, $goodsPhotos), 'qc_outcome' => $qc->disposition($accepted, $hold, $rejected), 'qc_completed_at' => now(),
            'additional_info' => $header['additionalInfo'], 'auto_close_po' => false,
            'status' => $accepted > 0 ? GoodsReceipt::STATUS_PROCESSING : ($rejected > 0 && $hold <= 0 ? GoodsReceipt::STATUS_QC_REJECTED : GoodsReceipt::STATUS_QC_HOLD),
            'payload_hash' => $payloadHash, 'attempted_at' => now(),
            'submitted_by' => $actorId, 'submitted_at' => now(), 'request_payload' => $payload,
        ]);
        if (! $receipt->wasRecentlyCreated) {
            return $receipt;
        }
        foreach ($items as $item) {
            $inspectedBy = $item['qcInspectedBy'] ?? $actorId;
            $record = $receipt->items()->create([
                'purchase_detail_id' => $item['purchaseDetailID'], 'product_id' => $item['productID'], 'product_detail_id' => $item['productDetailID'],
                'product_code' => $item['productCode'], 'product_name' => $item['productName'], 'uom_id' => $item['uomID'], 'uom_name' => $item['uomName'],
                'ordered_qty' => $item['orderedQty'], 'outstanding_qty' => $item['outstandingQty'], 'received_qty' => $item['acceptedQty'],
                'physical_qty' => $item['physicalQty'], 'accepted_qty' => $item['acceptedQty'], 'hold_qty' => $item['holdQty'], 'rejected_qty' => $item['rejectedQty'],
                'deviation_value' => $item['deviationVal'], 'measurement_method' => $item['measurementMethod'], 'variance_percentage' => $item['variancePercentage'],
                'tolerance_percentage' => $item['tolerancePercentage'], 'quantity_check_result' => $item['quantityResult'], 'color_check_result' => $item['colorResult'],
                'texture_check_result' => $item['textureResult'], 'packaging_check_result' => $item['packagingResult'], 'contamination_check_result' => $item['contaminationResult'],
                'temperature_category' => $item['temperatureCategory'], 'actual_temperature' => $item['actualTemperature'], 'min_temperature' => $item['minTemperature'],
                'max_temperature' => $item['maxTemperature'], 'cold_chain_result' => $item['coldChainResult'], 'shelf_life_required' => $item['shelfLifeRequired'],
                'minimum_shelf_life_percentage' => $item['minimumShelfLifePercentage'], 'shelf_life_result' => $item['shelfLifeResult'], 'sampling_required' => $item['samplingRequired'],
                'sampling_method' => $item['samplingMethod'], 'sampling_result' => $item['samplingResult'], 'sampling_notes' => $item['samplingNotes'],
                'disposition' => $qc->disposition((float) $item['acceptedQty'], (float) $item['holdQty'], (float) $item['rejectedQty']),
                'quarantine_location' => $item['quarantineLocation'], 'rejection_category' => $item['rejectionCategory'], 'rejection_reason' => $item['rejectionReason'],
                'evidence_photos' => $item['storedEvidencePhotos'], 'notes' => $item['notes'], 'qc_inspected_by' => $inspectedBy, 'qc_inspected_at' => $item['qcInspectedAt'] ?? now(),
            ]);
            // docs/products/inventory/receiving-simplification/prd.md §9/§15 "Pertahankan hasil QC rinci di
            // database": a quick row-level ED entered without going through the full
            // Bermasalah/batch workflow still needs to survive the save, so it becomes a single
            // implicit expiry record covering the whole accepted qty — only when the item has no
            // explicit batches of its own, so it never duplicates data the detailed workflow
            // already captured.
            $batches = $item['batches'] !== [] ? $item['batches'] : (filled($item['expiryDate']) ? [[
                'batchNumber' => null, 'manufacturedDate' => null, 'expiredDate' => $item['expiryDate'],
                'quantity' => $item['physicalQty'], 'acceptedQty' => $item['acceptedQty'], 'holdQty' => $item['holdQty'], 'rejectedQty' => $item['rejectedQty'],
                'shelfLifePercentage' => null,
            ]] : []);
            foreach ($batches as $batch) {
                $record->expiries()->create(['batch_number' => $batch['batchNumber'] ?: null, 'manufactured_date' => $batch['manufacturedDate'] ?: null,
                    'expired_date' => $batch['expiredDate'], 'quantity' => $batch['quantity'], 'accepted_quantity' => $batch['acceptedQty'], 'hold_quantity' => $batch['holdQty'],
                    'rejected_quantity' => $batch['rejectedQty'], 'shelf_life_remaining_percentage' => $batch['shelfLifePercentage'],
                    'qc_result' => $batch['shelfLifePercentage'] !== null && $batch['shelfLifePercentage'] >= (float) $item['minimumShelfLifePercentage'] ? 'pass' : 'fail']);
            }
            if ((float) $item['rejectedQty'] > 0) {
                $points = $qc->demeritPoints($item['rejectionCategory']);
                $record->vendorComplianceIncidents()->create(['goods_receipt_id' => $receipt->id, 'supplier_id' => $receipt->supplier_id, 'supplier_name' => $receipt->supplier_name,
                    'category' => $item['rejectionCategory'], 'severity' => $points >= 20 ? 'high' : ($points >= 10 ? 'medium' : 'low'), 'demerit_points' => $points,
                    'affected_quantity' => $item['rejectedQty'], 'status' => 'open', 'description' => $item['rejectionReason'],
                    'evidence_photos' => $item['storedEvidencePhotos'], 'reported_by' => $inspectedBy, 'occurred_at' => now()]);
            }
        }

        return $receipt;
    }

    private function isConnectionFailure(Throwable $exception): bool
    {
        do {
            if ($exception instanceof ConnectionException) {
                return true;
            }

            $exception = $exception->getPrevious();
        } while ($exception instanceof Throwable);

        return false;
    }
}
