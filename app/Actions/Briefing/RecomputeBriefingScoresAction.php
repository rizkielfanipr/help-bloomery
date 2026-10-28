<?php

namespace App\Actions\Briefing;

use App\Models\Branch;
use App\Models\BriefingScore;
use App\Models\User;
use App\Services\BriefingScoreCalculator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * docs/products/daily-briefing/scoring-ui/prd.md §13.6/Phase 9. The only path that persists a
 * BriefingScoreCalculator result — used by briefing:compute-scores and the workspace's "Hitung
 * Ulang" action, so the UI never has to assemble a command string or duplicate the
 * compute-then-save sequence. One branch's transaction failing never touches another branch's
 * row or its own previously-valid score (the transaction only wraps the new write; it never
 * deletes first).
 */
class RecomputeBriefingScoresAction
{
    /** Branches accepted per browser-initiated request; larger scopes belong to the command. */
    public const MAX_SCOPE = 200;

    /** Branches processed per batch/round-trip. */
    public const BATCH_SIZE = 10;

    public function __construct(private readonly BriefingScoreCalculator $calculator) {}

    /**
     * Branches the actor may recompute for, respecting branch scope.
     *
     * @return Collection<int, Branch>
     */
    public function branchesInScope(User $actor, ?int $branchId): Collection
    {
        return Branch::query()
            ->when(! $actor->canAccessAllBranches(), fn ($q) => $q->whereIn('id', $actor->accessibleBranchIds()))
            ->when($branchId, fn ($q) => $q->where('id', $branchId))
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  Collection<int, Branch>  $branches
     * @return array{computed: int, skipped: int, failed: int}
     */
    public function process(Collection $branches, int $year, int $month): array
    {
        $result = ['computed' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($branches as $branch) {
            try {
                $calculated = $this->calculator->calculate($branch, $year, $month);

                if ($calculated->isNotApplicable()) {
                    $result['skipped']++;

                    continue;
                }

                DB::transaction(function () use ($branch, $year, $month, $calculated): void {
                    BriefingScore::updateOrCreate(
                        ['branch_id' => $branch->id, 'year' => $year, 'month' => $month],
                        [
                            'score' => $calculated->score,
                            'breakdown' => $calculated->toBreakdownArray(),
                            'computed_at' => $calculated->computedAt,
                        ],
                    );
                });

                $result['computed']++;
            } catch (\Throwable $exception) {
                $result['failed']++;

                Log::error('briefing.recompute.branch_failed', [
                    'branch_id' => $branch->id,
                    'year' => $year,
                    'month' => $month,
                    'exception' => $exception::class,
                ]);
            }
        }

        return $result;
    }
}
