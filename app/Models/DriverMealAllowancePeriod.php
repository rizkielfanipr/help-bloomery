<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DriverMealAllowancePeriod extends Model
{
    protected $fillable = [
        'report_year', 'report_month', 'start_date', 'end_date', 'status', 'is_demo',
        'driver_count', 'trip_count', 'total_amount', 'created_by',
        'finalized_by', 'finalized_at', 'reopened_by', 'reopened_at', 'reopen_reason',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date', 'end_date' => 'date', 'total_amount' => 'decimal:2',
            'finalized_at' => 'datetime', 'reopened_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }

    public function summaries(): HasMany
    {
        return $this->hasMany(DriverMealAllowanceSummary::class, 'period_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DriverMealAllowanceTripItem::class, 'period_id');
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
