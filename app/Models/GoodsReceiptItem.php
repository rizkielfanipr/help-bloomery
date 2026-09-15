<?php

namespace App\Models;

use Database\Factories\GoodsReceiptItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GoodsReceiptItem extends Model
{
    /** @use HasFactory<GoodsReceiptItemFactory> */
    use HasFactory;

    protected $fillable = [
        'purchase_detail_id', 'product_id', 'product_detail_id', 'product_code', 'product_name',
        'uom_id', 'uom_name', 'ordered_qty', 'outstanding_qty', 'received_qty',
        'deviation_value', 'notes', 'esb_detail_id', 'physical_qty', 'accepted_qty', 'hold_qty',
        'rejected_qty', 'measurement_method', 'variance_percentage', 'tolerance_percentage',
        'quantity_check_result', 'color_check_result', 'texture_check_result',
        'packaging_check_result', 'contamination_check_result', 'temperature_category',
        'actual_temperature', 'min_temperature', 'max_temperature', 'cold_chain_result',
        'shelf_life_required', 'minimum_shelf_life_percentage', 'shelf_life_result',
        'sampling_required', 'sampling_method', 'sampling_result', 'sampling_notes',
        'disposition', 'quarantine_location', 'rejection_category', 'rejection_reason',
        'evidence_photos', 'qc_inspected_by', 'qc_inspected_at',
    ];

    protected function casts(): array
    {
        return [
            'ordered_qty' => 'decimal:4', 'outstanding_qty' => 'decimal:4',
            'received_qty' => 'decimal:4', 'deviation_value' => 'decimal:4',
            'physical_qty' => 'decimal:4', 'accepted_qty' => 'decimal:4', 'hold_qty' => 'decimal:4',
            'rejected_qty' => 'decimal:4', 'variance_percentage' => 'decimal:4',
            'tolerance_percentage' => 'decimal:4', 'actual_temperature' => 'decimal:2',
            'min_temperature' => 'decimal:2', 'max_temperature' => 'decimal:2',
            'shelf_life_required' => 'boolean', 'minimum_shelf_life_percentage' => 'decimal:2',
            'sampling_required' => 'boolean', 'evidence_photos' => 'array', 'qc_inspected_at' => 'datetime',
        ];
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function expiries(): HasMany
    {
        return $this->hasMany(GoodsReceiptExpiry::class);
    }

    public function vendorComplianceIncident(): HasOne
    {
        return $this->hasOne(VendorComplianceIncident::class);
    }
}
