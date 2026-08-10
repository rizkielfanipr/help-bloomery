<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReportShiftSubmission extends Model
{
    protected $fillable = [
        'sales_report_id',
        'shift_number',
        'submitted_by',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'shift_number' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    public function salesReport(): BelongsTo
    {
        return $this->belongsTo(SalesReport::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
