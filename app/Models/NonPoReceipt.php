<?php

namespace App\Models;

use App\Enums\NonPoReceiptStatus;
use Database\Factories\NonPoReceiptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NonPoReceipt extends Model
{
    /** @use HasFactory<NonPoReceiptFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'draft',
        'company_code' => 'BLSS',
        'document_type' => 'delivery_note',
        'document_match' => true,
    ];

    protected $fillable = [
        'receipt_number', 'submission_key', 'status', 'company_code', 'esb_branch_id', 'esb_branch_code', 'branch_name',
        'local_branch_id', 'location_id', 'location_name', 'receipt_date', 'supplier_text', 'supplier_contact', 'reason',
        'document_type', 'document_number', 'document_date', 'document_match', 'document_notes', 'document_photos',
        'goods_photos', 'additional_info', 'payload_hash', 'created_by', 'submitted_by', 'submitted_at', 'processed_by',
        'processed_at', 'po_number', 'po_date', 'po_supplier_id', 'po_supplier_name', 'po_snapshot', 'po_linked_by',
        'po_linked_at', 'supplier_mismatch_confirmed_by', 'supplier_mismatch_confirmed_at', 'goods_receipt_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => NonPoReceiptStatus::class,
            'receipt_date' => 'date', 'document_date' => 'date', 'po_date' => 'date',
            'document_match' => 'boolean', 'document_photos' => 'array', 'goods_photos' => 'array', 'po_snapshot' => 'array',
            'submitted_at' => 'datetime', 'processed_at' => 'datetime', 'po_linked_at' => 'datetime', 'supplier_mismatch_confirmed_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(NonPoReceiptItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(NonPoReceiptStatusHistory::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function localBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'local_branch_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function poLinkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'po_linked_by');
    }

    public function supplierMismatchConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supplier_mismatch_confirmed_by');
    }

    /** Every stored photo of this record, so replaced files can be cleaned up after commit. */
    public function storedPhotoPaths(): array
    {
        return collect($this->document_photos ?? [])
            ->merge($this->goods_photos ?? [])
            ->merge($this->items->flatMap(fn (NonPoReceiptItem $item): array => $item->evidence_photos ?? []))
            ->values()
            ->all();
    }
}
