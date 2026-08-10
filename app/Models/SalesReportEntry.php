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
        'shift_number',
        'payment_method_name',
        'label',
        'sales_store_amount',
        'notes',
    ];

    protected $casts = [
        'shift_number' => 'integer',
        'sales_store_amount' => 'decimal:2',
    ];

    public function salesReport(): BelongsTo
    {
        return $this->belongsTo(SalesReport::class);
    }
}
