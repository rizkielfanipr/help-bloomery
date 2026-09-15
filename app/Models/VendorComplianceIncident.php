<?php

namespace App\Models;

use Database\Factories\VendorComplianceIncidentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class VendorComplianceIncident extends Model
{
    /** @use HasFactory<VendorComplianceIncidentFactory> */
    use HasFactory;

    protected $fillable = [
        'incident_number', 'goods_receipt_id', 'goods_receipt_item_id', 'supplier_id',
        'supplier_name', 'category', 'severity', 'demerit_points', 'affected_quantity',
        'status', 'action_type', 'follow_up_notes', 'handled_by', 'resolved_at',
        'description', 'evidence_photos', 'reported_by', 'occurred_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $incident): void {
            $incident->incident_number ??= 'VCI-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        });
    }

    protected function casts(): array
    {
        return [
            'affected_quantity' => 'decimal:4', 'evidence_photos' => 'array',
            'occurred_at' => 'datetime', 'resolved_at' => 'datetime',
        ];
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptItem::class, 'goods_receipt_item_id');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
