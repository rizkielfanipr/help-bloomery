<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EsbPurchaseOrder extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'datetime',
            'required_date' => 'datetime',
            'esb_edited_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'rate' => 'decimal:4',
            'purchase_total' => 'decimal:4',
            'raw_payload' => 'array',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(EsbPurchaseOrderItem::class);
    }
}
