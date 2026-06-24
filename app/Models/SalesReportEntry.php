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
        'payment_method_id',
        'shift_1_amount',
        'shift_2_amount',
        'notes',
    ];

    protected $casts = [
        'shift_1_amount' => 'decimal:2',
        'shift_2_amount' => 'decimal:2',
    ];

    public function salesReport(): BelongsTo
    {
        return $this->belongsTo(SalesReport::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
