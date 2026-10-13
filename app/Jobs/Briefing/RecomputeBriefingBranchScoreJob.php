<?php

namespace App\Jobs\Briefing;

use App\Actions\Briefing\RecomputeBriefingScoresAction;
use App\Models\Branch;
use App\Models\BriefingRecord;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * docs/daily-briefing-bundle-approval-prd.md §11.5/§18: recomputes one branch/month after a bundle
 * is approved, rejected or reopened. Unique per branch and month only until it starts running: a
 * burst of reviews collapses into one pending recalculation (which reads the latest state when it
 * runs), while a review that lands mid-run still queues a fresh one. The scheduled monthly compute
 * stays as the reconciliation fallback.
 */
class RecomputeBriefingBranchScoreJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $branchId,
        public int $year,
        public int $month,
    ) {}

    /**
     * Queued only once the surrounding transaction commits. A weekly bundle can straddle two
     * months, and both months' scores depend on it.
     */
    public static function dispatchForBundle(BriefingRecord $bundle): void
    {
        collect([$bundle->period_start, $bundle->period_end ?? $bundle->period_start])
            ->map(fn (CarbonInterface $date): string => $date->format('Y-n'))
            ->unique()
            ->each(function (string $yearMonth) use ($bundle): void {
                [$year, $month] = array_map('intval', explode('-', $yearMonth));

                static::dispatch((int) $bundle->branch_id, $year, $month)->afterCommit();
            });
    }

    public function uniqueId(): string
    {
        return "{$this->branchId}:{$this->year}:{$this->month}";
    }

    public function handle(RecomputeBriefingScoresAction $action): void
    {
        $branch = Branch::query()->find($this->branchId);

        if ($branch === null) {
            return;
        }

        $action->process(collect([$branch]), $this->year, $this->month);
    }
}
