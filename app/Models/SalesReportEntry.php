<?php

namespace App\Models;

use Database\Factories\SalesReportEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReportEntry extends Model
{
    /** @use HasFactory<SalesReportEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'sales_report_id',
        'payment_method_name',
        'sales_system_amount',
        'sales_store_amount',
        'notes',
    ];

    protected $casts = [
        'sales_system_amount' => 'decimal:2',
        'sales_store_amount' => 'decimal:2',
    ];

    public function salesReport(): BelongsTo
    {
        return $this->belongsTo(SalesReport::class);
    }

    public function getSelisihAttribute(): float
    {
        return (float) $this->sales_system_amount - (float) $this->sales_store_amount;
    }
}
