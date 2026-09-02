<?php

namespace App\Models;

use Database\Factories\BasketSizeRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BasketSizeRecord extends Model
{
    /** @use HasFactory<BasketSizeRecordFactory> */
    use HasFactory;

    protected $fillable = [
        'sales_report_id', 'branch_id', 'branch_sales_shift_id', 'report_date',
        'shift_number', 'shift_name', 'shift_start_time', 'shift_end_time',
        'revenue', 'total_pax', 'basket_size', 'staff_count', 'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'revenue' => 'decimal:2',
            'basket_size' => 'decimal:2',
            'calculated_at' => 'datetime',
        ];
    }

    public function salesReport(): BelongsTo
    {
        return $this->belongsTo(SalesReport::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(BranchSalesShift::class, 'branch_sales_shift_id');
    }

    public function employeeRecords(): HasMany
    {
        return $this->hasMany(BasketSizeEmployeeRecord::class);
    }
}
