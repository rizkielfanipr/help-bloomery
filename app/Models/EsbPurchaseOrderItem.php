<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EsbPurchaseOrderItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'conversion_qty' => 'decimal:4',
            'stock_qty' => 'decimal:4',
            'pricelist_price' => 'decimal:4',
            'price' => 'decimal:4',
            'discount' => 'decimal:4',
            'discount_percent' => 'decimal:4',
            'vat' => 'decimal:4',
            'total' => 'decimal:4',
            'last_price' => 'decimal:4',
            'last_price_date' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(EsbPurchaseOrder::class, 'esb_purchase_order_id');
    }

    public function normalizedNetPrice(): float
    {
        $baseQty = (float) $this->stock_qty > 0
            ? (float) $this->stock_qty
            : (float) $this->qty * max(1, (float) $this->conversion_qty);

        return $baseQty > 0
            ? (((float) $this->total - (float) $this->vat) * (float) ($this->purchaseOrder?->rate ?: 1)) / $baseQty
            : 0;
    }
}
