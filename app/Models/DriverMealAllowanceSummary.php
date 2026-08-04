<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DriverMealAllowanceSummary extends Model
{
    protected $fillable = [
        'period_id', 'driver_id', 'trip_count', 'base_amount', 'adjustment_amount',
        'adjustment_reason', 'final_amount', 'adjusted_by', 'adjusted_at',
    ];

    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:2', 'adjustment_amount' => 'decimal:2',
            'final_amount' => 'decimal:2', 'adjusted_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(DriverMealAllowancePeriod::class, 'period_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DriverMealAllowanceTripItem::class, 'summary_id');
    }
}
