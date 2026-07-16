<?php

namespace App\Models;

use Database\Factories\StockCardEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCardEntry extends Model
{
    /** @use HasFactory<StockCardEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'stock_card_id',
        'product_code',
        'product_name',
        'system_qty',
        'system_unit',
        'actual_qty',
        'notes',
    ];

    protected $casts = [
        'system_qty' => 'decimal:4',
        'actual_qty' => 'decimal:4',
    ];

    public function stockCard(): BelongsTo
    {
        return $this->belongsTo(StockCard::class);
    }

    public function getVarianceAttribute(): ?float
    {
        if ($this->actual_qty === null) {
            return null;
        }

        return (float) $this->actual_qty - (float) $this->system_qty;
    }
}
