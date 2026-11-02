<?php

namespace App\Models;

use Database\Factories\NonPoReceiptItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NonPoReceiptItem extends Model
{
    /** @use HasFactory<NonPoReceiptItemFactory> */
    use HasFactory;

    protected $fillable = [
        'non_po_receipt_id', 'product_id', 'product_detail_id', 'product_code', 'product_name', 'uom_name', 'physical_qty', 'accepted_qty',
        'hold_qty', 'rejected_qty', 'invoice_price', 'disposition', 'qc_data', 'evidence_photos', 'notes', 'qc_inspected_by',
        'qc_inspected_at', 'purchase_detail_id', 'po_uom_id', 'po_uom_name', 'po_ordered_qty', 'po_outstanding_qty', 'po_deviation_value',
    ];

    protected function casts(): array
    {
        return [
            'physical_qty' => 'decimal:4', 'accepted_qty' => 'decimal:4', 'hold_qty' => 'decimal:4', 'rejected_qty' => 'decimal:4',
            'invoice_price' => 'decimal:4', 'po_ordered_qty' => 'decimal:4', 'po_outstanding_qty' => 'decimal:4', 'po_deviation_value' => 'decimal:4',
            'qc_data' => 'array', 'evidence_photos' => 'array', 'qc_inspected_at' => 'datetime',
        ];
    }

    public function nonPoReceipt(): BelongsTo
    {
        return $this->belongsTo(NonPoReceipt::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(NonPoReceiptBatch::class);
    }

    public function qcInspectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qc_inspected_by');
    }

    /** UOM comparison is by name because Master Product exposes no UOM ID (prd.md §29). */
    public static function normalizeUom(?string $uom): string
    {
        return mb_strtolower(trim((string) $uom));
    }

    /**
     * The stored item in the array shape Receiving QC reads, so draft, submit, and the shared Goods
     * Receipt Action assess one representation.
     *
     * @return array<string, mixed>
     */
    public function toReceivingItem(): array
    {
        return array_replace(self::defaultQcData(), $this->qc_data ?? [], [
            'selected' => true,
            'productID' => (int) $this->product_id, 'productDetailID' => (int) $this->product_detail_id,
            'productCode' => (string) $this->product_code, 'productName' => $this->product_name,
            'uomName' => $this->uom_name, 'uomID' => $this->po_uom_id,
            'purchaseDetailID' => $this->purchase_detail_id,
            'orderedQty' => (float) $this->po_ordered_qty, 'outstandingQty' => $this->po_outstanding_qty === null ? null : (float) $this->po_outstanding_qty,
            'physicalQty' => (float) $this->physical_qty, 'acceptedQty' => (float) $this->accepted_qty,
            'holdQty' => (float) $this->hold_qty, 'rejectedQty' => (float) $this->rejected_qty,
            'invoicePrice' => $this->invoice_price === null ? null : (float) $this->invoice_price,
            'deviationVal' => (float) $this->po_deviation_value, 'notes' => (string) $this->notes,
            'evidencePhotos' => $this->evidence_photos ?? [], 'storedEvidencePhotos' => $this->evidence_photos ?? [],
            'batches' => $this->batches->map(fn (NonPoReceiptBatch $batch): array => [
                'batchNumber' => (string) $batch->batch_number,
                'manufacturedDate' => (string) $batch->manufactured_date?->toDateString(),
                'expiredDate' => (string) $batch->expired_date?->toDateString(),
                'quantity' => (float) $batch->quantity, 'acceptedQty' => (float) $batch->accepted_qty,
                'holdQty' => (float) $batch->hold_qty, 'rejectedQty' => (float) $batch->rejected_qty,
            ])->all(),
        ]);
    }

    /**
     * QC fields kept as a read-back snapshot in `qc_data`, with the values Receiving treats as pass.
     *
     * @return array<string, mixed>
     */
    public static function defaultQcData(): array
    {
        return [
            'condition' => 'ok', 'expiryDate' => '', 'measurementMethod' => 'count', 'tolerancePercentage' => 0,
            'colorResult' => 'pass', 'textureResult' => 'pass', 'packagingResult' => 'pass', 'contaminationResult' => 'pass',
            'temperatureCategory' => 'ambient', 'actualTemperature' => null, 'minTemperature' => null, 'maxTemperature' => null,
            'shelfLifeRequired' => false, 'minimumShelfLifePercentage' => 80,
            'samplingRequired' => false, 'samplingMethod' => '', 'samplingResult' => 'pass', 'samplingNotes' => '',
            'quarantineLocation' => '', 'rejectionCategory' => '', 'rejectionReason' => '',
        ];
    }
}
