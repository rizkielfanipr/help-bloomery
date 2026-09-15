<?php

namespace App\Models;

use Database\Factories\GoodsReceiptExpiryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptExpiry extends Model
{
    /** @use HasFactory<GoodsReceiptExpiryFactory> */
    use HasFactory;

    protected $fillable = [
        'batch_number', 'manufactured_date', 'expired_date', 'quantity', 'accepted_quantity',
        'hold_quantity', 'rejected_quantity', 'shelf_life_remaining_percentage', 'qc_result',
    ];

    protected function casts(): array
    {
        return [
            'manufactured_date' => 'date', 'expired_date' => 'date', 'quantity' => 'decimal:4',
            'accepted_quantity' => 'decimal:4', 'hold_quantity' => 'decimal:4',
            'rejected_quantity' => 'decimal:4', 'shelf_life_remaining_percentage' => 'decimal:2',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptItem::class, 'goods_receipt_item_id');
    }
}
