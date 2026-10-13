<?php

namespace App\Models;

use App\Enums\BriefingBundleStatus;
use App\Enums\BriefingPeriod;
use Carbon\CarbonInterface;
use Database\Factories\BriefingRecordFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BriefingRecord extends Model
{
    /** @use HasFactory<BriefingRecordFactory> */
    use HasFactory;

    /**
     * `user_id` stays the legacy owner / creator of the row; the actual contributors of a branch
     * bundle live on each item's `submitted_by` (docs/daily-briefing-bundle-approval-prd.md §7.1).
     */
    protected $fillable = [
        'user_id',
        'branch_id',
        'period',
        'record_date',
        'period_start',
        'period_end',
        'status',
        'submitted_at',
        'completed_at',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'scoring_method_version',
    ];

    protected $casts = [
        'period' => BriefingPeriod::class,
        'record_date' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
        'status' => BriefingBundleStatus::class,
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'scoring_method_version' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(BriefingRecordRequirement::class);
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(BriefingRecordTransition::class);
    }

    /**
     * docs/daily-briefing-bundle-approval-prd.md Phase 8: complete bundles left waiting for a
     * supervisor longer than the review SLA. Drafts are never included — they were never in review.
     */
    public function scopeAwaitingReviewPastSla(Builder $query, int $days): void
    {
        $query->where('status', BriefingBundleStatus::SupervisorReview->value)
            ->whereNotNull('completed_at')
            ->where('completed_at', '<=', now()->subDays($days));
    }

    /**
     * Rows that predate the bundle schema have no status until the grouping command classifies them.
     */
    public function isLegacyUngrouped(): bool
    {
        return $this->status === null;
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
