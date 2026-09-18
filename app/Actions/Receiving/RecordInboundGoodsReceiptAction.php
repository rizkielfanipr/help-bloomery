<?php

namespace App\Actions\Receiving;

use App\Models\GoodsReceipt;
use App\Models\User;
use App\Services\Receiving\InboundGoodsReceiptQcService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecordInboundGoodsReceiptAction
{
    public function __construct(private readonly InboundGoodsReceiptQcService $qc) {}

    /**
     * @param  Collection<int, array<string, mixed>>  $items  Validated and assessed QC items with stored evidence paths.
     * @param  list<string>  $documentPhotos
     * @param  array<string, mixed>  $form  Validated receiving form context.
     */
    public function execute(User $actor, string $po, array $location, array $payload, Collection $items, array $documentPhotos, array $form): GoodsReceipt
    {
        $qc = $this->qc;

        return DB::transaction(function () use ($actor, $po, $location, $payload, $items, $documentPhotos, $form, $qc): GoodsReceipt {
            $accepted = (float) $items->sum('acceptedQty');
            $hold = (float) $items->sum('holdQty');
            $rejected = (float) $items->sum('rejectedQty');
            $receipt = GoodsReceipt::create([
                'company_code' => 'BLSS', 'reference_number' => $po, 'purchase_date' => data_get($form['purchaseOrder'], 'purchaseDate'), 'goods_receipt_date' => $form['goodsReceiptDate'],
                'branch_id' => data_get($form['purchaseOrder'], 'branchID'), 'branch_name' => data_get($form['purchaseOrder'], 'branchName'),
                'supplier_id' => data_get($form['purchaseOrder'], 'supplierID'), 'supplier_name' => data_get($form['purchaseOrder'], 'supplierName'),
                'location_id' => $location['locationID'], 'location_name' => $location['locationName'], 'delivery_number' => $form['deliveryNumber'], 'delivery_date' => $form['deliveryDate'],
                'invoice_status' => $form['invoiceStatus'], 'invoice_number' => $form['invoiceNumber'] ?: null, 'invoice_date' => $form['invoiceDate'] ?: null,
                'po_document_match' => $form['poDocumentMatch'], 'delivery_document_match' => $form['deliveryDocumentMatch'],
                'invoice_document_match' => $form['invoiceDocumentMatch'], 'price_match' => $form['priceMatch'], 'document_notes' => $form['documentNotes'],
                'document_evidence_photos' => $documentPhotos, 'qc_outcome' => $qc->disposition($accepted, $hold, $rejected), 'qc_completed_at' => now(),
                'additional_info' => $form['additionalInfo'], 'auto_close_po' => $form['autoClosePo'],
                'status' => $accepted > 0 ? GoodsReceipt::STATUS_PROCESSING : ($rejected > 0 && $hold <= 0 ? GoodsReceipt::STATUS_QC_REJECTED : GoodsReceipt::STATUS_QC_HOLD),
                'submitted_by' => $actor->id, 'submitted_at' => now(), 'request_payload' => $payload,
            ]);
            foreach ($items as $item) {
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
                    'evidence_photos' => $item['storedEvidencePhotos'], 'notes' => $item['notes'], 'qc_inspected_by' => $actor->id, 'qc_inspected_at' => now(),
                ]);
                foreach ($item['batches'] as $batch) {
                    $record->expiries()->create(['batch_number' => $batch['batchNumber'] ?: null, 'manufactured_date' => $batch['manufacturedDate'] ?: null,
                        'expired_date' => $batch['expiredDate'], 'quantity' => $batch['quantity'], 'accepted_quantity' => $batch['acceptedQty'], 'hold_quantity' => $batch['holdQty'],
                        'rejected_quantity' => $batch['rejectedQty'], 'shelf_life_remaining_percentage' => $batch['shelfLifePercentage'],
                        'qc_result' => $batch['shelfLifePercentage'] !== null && $batch['shelfLifePercentage'] >= (float) $item['minimumShelfLifePercentage'] ? 'pass' : 'fail']);
                }
                if ((float) $item['rejectedQty'] > 0) {
                    $points = $qc->demeritPoints($item['rejectionCategory']);
                    $record->vendorComplianceIncident()->create(['goods_receipt_id' => $receipt->id, 'supplier_id' => $receipt->supplier_id, 'supplier_name' => $receipt->supplier_name,
                        'category' => $item['rejectionCategory'], 'severity' => $points >= 20 ? 'high' : ($points >= 10 ? 'medium' : 'low'), 'demerit_points' => $points,
                        'affected_quantity' => $item['rejectedQty'], 'status' => 'open', 'description' => $item['rejectionReason'],
                        'evidence_photos' => $item['storedEvidencePhotos'], 'reported_by' => $actor->id, 'occurred_at' => now()]);
                }
            }

            return $receipt;
        });
    }
}
