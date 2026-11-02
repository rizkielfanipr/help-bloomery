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
        'product_category',
        'system_qty',
        'system_unit',
        'is_manual',
        'actual_qty',
        'reported_qty',
        'notes',
        'supervisor_notes',
    ];

    protected $casts = [
        'system_qty' => 'decimal:4',
        'actual_qty' => 'decimal:4',
        'reported_qty' => 'decimal:4',
        'is_manual' => 'boolean',
    ];

    /** Empty means not counted yet, which is valid in a draft; zero is a real count. */
    public static function isValidQuantity(mixed $value): bool
    {
        if (is_array($value) || is_object($value) || is_bool($value)) {
            return false;
        }

        $quantity = trim((string) $value);

        return $quantity === ''
            || preg_match('/^(?:\d{1,16}(?:\.\d{0,4})?|\.\d{1,4})$/D', $quantity) === 1;
    }

    public static function normalizeQuantity(mixed $value): ?string
    {
        $quantity = rtrim(trim((string) $value), '.');

        if ($quantity === '') {
            return null;
        }

        return str_starts_with($quantity, '.') ? '0'.$quantity : $quantity;
    }

    public function stockCard(): BelongsTo
    {
        return $this->belongsTo(StockCard::class);
    }

    /** Difference between the working qty (staff's report, or the Supervisor's correction) and ESB's system qty. */
    public function getVarianceAttribute(): ?float
    {
        if ($this->actual_qty === null || $this->system_qty === null) {
            return null;
        }

        return (float) $this->actual_qty - (float) $this->system_qty;
    }
}
