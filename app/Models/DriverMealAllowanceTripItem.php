<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverMealAllowanceTripItem extends Model
{
    protected $fillable = [
        'period_id', 'summary_id', 'trip_id', 'trip_date', 'trip_code', 'route_name',
        'allowance_amount', 'amount_source', 'is_included', 'exclusion_reason',
    ];

    protected function casts(): array
    {
        return [
            'trip_date' => 'date', 'allowance_amount' => 'decimal:2', 'is_included' => 'boolean',
        ];
    }

    public function summary(): BelongsTo
    {
        return $this->belongsTo(DriverMealAllowanceSummary::class, 'summary_id');
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
