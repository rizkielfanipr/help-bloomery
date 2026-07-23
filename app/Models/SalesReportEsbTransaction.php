<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReportEsbTransaction extends Model
{
    protected $fillable = [
        'sales_report_id',
        'sales_num',
        'sales_date_out',
        'payment_total',
    ];

    protected function casts(): array
    {
        return [
            'sales_date_out' => 'datetime',
            'payment_total' => 'decimal:2',
        ];
    }

    public function salesReport(): BelongsTo
    {
        return $this->belongsTo(SalesReport::class);
    }
}
