<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReportEmployee extends Model
{
    protected $fillable = [
        'sales_report_id',
        'employee_id',
        'employee_code',
        'employee_name',
        'employee_position',
    ];

    public function salesReport(): BelongsTo
    {
        return $this->belongsTo(SalesReport::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
