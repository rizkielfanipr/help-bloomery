<?php

namespace App\Models;

use Database\Factories\StockCardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockCard extends Model
{
    /** @use HasFactory<StockCardFactory> */
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'submitted_by',
        'report_date',
        'flag_unit',
        'submitted_at',
    ];

    protected $casts = [
        'report_date' => 'date',
        'submitted_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(StockCardEntry::class);
    }

    public function getTotalVarianceItemsAttribute(): int
    {
        return $this->entries->filter(fn ($e) => $e->actual_qty !== null && (float) $e->actual_qty !== (float) $e->system_qty)->count();
    }
}
