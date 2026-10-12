<?php

namespace App\Models;

use App\Enums\BriefingPeriod;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BriefingRecord extends Model
{
    protected $fillable = ['user_id', 'branch_id', 'period', 'record_date', 'submitted_at'];

    protected $casts = [
        'period' => BriefingPeriod::class,
        'record_date' => 'date',
        'submitted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    protected static function booted(): void
    {
        static::creating(function (self $record): void {
            if ($record->branch_id === null) {
                $record->branch_id = User::query()->find($record->user_id)?->branch_id;
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(BriefingItem::class);
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    public function isFullyComplete(): bool
    {
        return $this->items->every(fn (BriefingItem $item) => $item->is_completed);
    }

    public function completedCount(): int
    {
        return $this->items->filter(fn (BriefingItem $item) => $item->is_completed)->count();
    }

    /**
     * docs/daily-briefing-remediation-prd.md §4/§9 (unit kewajiban = per branch): the single
     * canonical record for a branch/period/date, regardless of which participant created it. The
     * oldest record is canonical so a settled "who owns this record" question never flips once
     * more than one record exists for the scope (legacy data predating per-branch canonicalization
     * may have more than one).
     */
    public static function canonicalForBranch(int $branchId, BriefingPeriod $period, CarbonInterface $recordDate): ?self
    {
        return static::query()
            ->where('branch_id', $branchId)
            ->where('period', $period->value)
            ->whereDate('record_date', $recordDate)
            ->oldest('id')
            ->first();
    }
}
