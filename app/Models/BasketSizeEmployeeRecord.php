<?php

namespace App\Models;

use Database\Factories\BasketSizeEmployeeRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BasketSizeEmployeeRecord extends Model
{
    /** @use HasFactory<BasketSizeEmployeeRecordFactory> */
    use HasFactory;

    protected $fillable = [
        'basket_size_record_id', 'sales_report_id', 'employee_id', 'employee_code',
        'employee_name', 'employee_position', 'basket_size_credit',
    ];

    protected function casts(): array
    {
        return ['basket_size_credit' => 'decimal:2'];
    }

    public function basketSizeRecord(): BelongsTo
    {
        return $this->belongsTo(BasketSizeRecord::class);
    }

    public function salesReport(): BelongsTo
    {
        return $this->belongsTo(SalesReport::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
