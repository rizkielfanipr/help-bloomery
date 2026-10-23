<?php

namespace App\Models;

use App\Enums\BriefingPeriod;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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
        return static::forBranchPeriod($branchId, $period, $recordDate)
            ->oldest('id')
            ->first();
    }

    /** @return Builder<self> */
    public static function forBranchPeriod(int $branchId, BriefingPeriod $period, CarbonInterface $recordDate): Builder
    {
        [$startDate, $endDate] = static::periodBounds($period, $recordDate);

        return static::query()
            ->where('branch_id', $branchId)
            ->where('period', $period->value)
            ->whereDate('record_date', '>=', $startDate->toDateString())
            ->whereDate('record_date', '<=', $endDate->toDateString());
    }

    /**
     * Return one visible item per task while remaining compatible with legacy periods that were
     * stored as multiple daily records. Completed submissions win over generated empty slots.
     *
     * @return Collection<string, BriefingItem>
     */
    public static function itemsForBranchPeriod(int $branchId, BriefingPeriod $period, CarbonInterface $recordDate): Collection
    {
        return static::forBranchPeriod($branchId, $period, $recordDate)
            ->with('items')
            ->oldest('id')
            ->get()
            ->flatMap(fn (self $record): Collection => $record->items)
            ->sortBy(fn (BriefingItem $item): array => [
                $item->is_completed ? 1 : 0,
                $item->completed_at?->getTimestamp() ?? 0,
                $item->id,
            ])
            ->keyBy(fn (BriefingItem $item): string => $item->task_key);
    }

    public static function periodStartDate(BriefingPeriod $period, CarbonInterface $recordDate): Carbon
    {
        return static::periodBounds($period, $recordDate)[0];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private static function periodBounds(BriefingPeriod $period, CarbonInterface $recordDate): array
    {
        $date = Carbon::parse($recordDate->toDateString());

        return match ($period) {
            BriefingPeriod::Daily => [$date->copy()->startOfDay(), $date->copy()->endOfDay()],
            BriefingPeriod::Weekly => [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()],
            BriefingPeriod::Monthly => [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()],
        };
    }
}
