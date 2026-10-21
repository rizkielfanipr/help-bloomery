<?php

namespace App\Models;

use App\Enums\OrderingSyncStatus;
use Database\Factories\OrderingForecastRunFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Immutable forecast run of one branch (docs/ordering-recommendation-prd.md §11.3).
 */
class OrderingForecastRun extends Model
{
    /** @use HasFactory<OrderingForecastRunFactory> */
    use HasFactory;

    protected $fillable = [
        'idempotency_key', 'branch_id', 'horizon_days', 'history_from', 'history_to', 'forecast_from', 'forecast_to',
        'algorithm_version', 'configuration_hash', 'configuration_snapshot', 'data_cutoff_at', 'status', 'completeness',
        'items_count', 'generated_at', 'error_summary', 'correlation_id',
    ];

    protected function casts(): array
    {
        return [
            'horizon_days' => 'integer',
            'history_from' => 'date',
            'history_to' => 'date',
            'forecast_from' => 'date',
            'forecast_to' => 'date',
            'configuration_snapshot' => 'array',
            'completeness' => 'array',
            'data_cutoff_at' => 'datetime',
            'generated_at' => 'datetime',
            'status' => OrderingSyncStatus::class,
        ];
    }

    /** Completed runs only; the newest one per branch and horizon is the active recommendation. */
    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', OrderingSyncStatus::Completed->value);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderingForecastItem::class);
    }
}
