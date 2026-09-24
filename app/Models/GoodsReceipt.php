<?php

namespace App\Models;

use Database\Factories\GoodsReceiptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceipt extends Model
{
    /** @use HasFactory<GoodsReceiptFactory> */
    use HasFactory;

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_UNKNOWN = 'unknown';

    public const STATUS_QC_HOLD = 'qc_hold';

    public const STATUS_QC_REJECTED = 'qc_rejected';

    public const STATUS_PARTIAL_SUCCEEDED = 'partial_succeeded';

    protected $fillable = [
        'company_code', 'reference_number', 'esb_goods_receipt_number', 'purchase_date',
        'goods_receipt_date', 'esb_branch_id', 'local_branch_id', 'branch_name', 'supplier_id', 'supplier_name',
        'location_id', 'location_name', 'delivery_number', 'additional_info', 'selected_asset_ids',
        'auto_close_po', 'status', 'submission_key', 'payload_hash', 'attempted_at', 'submitted_by', 'submitted_at', 'synced_at', 'esb_code',
        'esb_message', 'request_payload', 'response_payload', 'sync_error', 'delivery_date',
        'invoice_status', 'invoice_number', 'invoice_date', 'po_document_match',
        'delivery_document_match', 'invoice_document_match', 'price_match', 'document_notes',
        'document_evidence_photos', 'qc_outcome', 'qc_completed_at',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date', 'goods_receipt_date' => 'date', 'auto_close_po' => 'boolean',
            'attempted_at' => 'datetime', 'submitted_at' => 'datetime', 'synced_at' => 'datetime', 'request_payload' => 'array',
            'response_payload' => 'array', 'delivery_date' => 'date', 'invoice_date' => 'date',
            'po_document_match' => 'boolean', 'delivery_document_match' => 'boolean',
            'invoice_document_match' => 'boolean', 'price_match' => 'boolean',
            'document_evidence_photos' => 'array', 'qc_completed_at' => 'datetime',
        ];
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function localBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'local_branch_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function vendorComplianceIncidents(): HasMany
    {
        return $this->hasMany(VendorComplianceIncident::class);
    }
}
