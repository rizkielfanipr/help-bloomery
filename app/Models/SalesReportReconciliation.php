<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReportReconciliation extends Model
{
    protected $fillable = [
        'sales_report_id',
        'payment_method_name',
        'reported_store_amount',
        'store_amount',
        'system_amount',
        'settlement_amount',
        'mdr_percentage',
        'mdr_amount',
        'expected_settlement_amount',
        'settlement_difference',
        'reconciliation_status',
        'supervisor_notes',
        'finance_note',
        'system_fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'reported_store_amount' => 'decimal:2',
            'store_amount' => 'decimal:2',
            'system_amount' => 'decimal:2',
            'settlement_amount' => 'decimal:2',
            'mdr_percentage' => 'decimal:4',
            'mdr_amount' => 'decimal:2',
            'expected_settlement_amount' => 'decimal:2',
            'settlement_difference' => 'decimal:2',
            'system_fetched_at' => 'datetime',
        ];
    }

    public function salesReport(): BelongsTo
    {
        return $this->belongsTo(SalesReport::class);
    }

    public function getSelisihAttribute(): float
    {
        return (float) $this->system_amount - (float) $this->store_amount;
    }
}
