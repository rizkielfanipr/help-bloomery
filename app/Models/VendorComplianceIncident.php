<?php

namespace App\Models;

use App\Enums\VendorComplianceLiabilityStatus;
use Database\Factories\VendorComplianceIncidentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'source_type', 'source_key', 'liability_status', 'liability_reviewed_by', 'liability_reviewed_at', 'waiver_reason',
        'required_date', 'first_late_receipt_date', 'latest_late_receipt_date', 'delay_days', 'scoring_rule_version',
    ];

    public const CATEGORY_LATE_DELIVERY = 'late_delivery';

    protected $attributes = [
        'liability_status' => 'not_applicable',
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
            'liability_status' => VendorComplianceLiabilityStatus::class, 'liability_reviewed_at' => 'datetime',
            'required_date' => 'date', 'first_late_receipt_date' => 'date', 'latest_late_receipt_date' => 'date', 'delay_days' => 'integer',
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

    public function liabilityReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'liability_reviewed_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(VendorComplianceIncidentEvent::class)->orderBy('id');
    }

    public function isLateDelivery(): bool
    {
        return $this->category === self::CATEGORY_LATE_DELIVERY;
    }

    /** The stable key of the one Late Delivery incident per company and PO (PRD §14.2). */
    public static function lateDeliverySourceKey(string $companyCode, string $purchaseNumber): string
    {
        return self::CATEGORY_LATE_DELIVERY.':'.Str::upper(trim($companyCode)).':'.Str::upper(trim($purchaseNumber));
    }
}
