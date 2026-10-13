<?php

namespace App\Services;

use App\Enums\BriefingBundleStatus;
use App\Enums\BriefingPeriod;
use App\Enums\BriefingTransitionSource;
use App\Exceptions\Briefing\BriefingBundleConflictException;
use App\Exceptions\Briefing\BriefingBundleUnavailableException;
use App\Models\Branch;
use App\Models\BriefingRecord;
use App\Models\BriefingRecordRequirement;
use App\Models\BriefingTask;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * docs/daily-briefing-bundle-approval-prd.md §7/§17/§18: finds or creates the one canonical bundle
 * for branch_id + period + period_start. It never creates submission items — the obligations live
 * in the requirement snapshot — and it never picks between competing rows: an ambiguous key is a
 * conflict for reconciliation, not something to guess at runtime.
 */
class BriefingBundleResolver
{
    private const LOCK_SECONDS = 10;

    private const LOCK_WAIT_SECONDS = 5;

    public function __construct(
        private readonly BriefingAssignmentService $assignments,
        private readonly BriefingPeriodNormalizer $periods,
    ) {}

    /**
     * Read-only lookup of the canonical bundle containing $date.
     *
     * @throws BriefingBundleConflictException when the business key is not unique
     */
    public function find(int $branchId, BriefingPeriod $period, CarbonInterface $date): ?BriefingRecord
    {
        $bundles = BriefingRecord::query()
            ->where('branch_id', $branchId)
            ->where('period', $period->value)
            ->whereDate('period_start', $this->periods->start($period, $date))
            ->get();

        if ($bundles->count() > 1) {
            throw new BriefingBundleConflictException($bundles->modelKeys());
        }

        return $bundles->first();
    }

    /**
     * @throws BriefingBundleUnavailableException when the branch/actor is not a participant or nothing is assigned
     * @throws BriefingBundleConflictException when the business key, or the legacy row it would adopt, is ambiguous
     */
    public function findOrCreate(Branch $branch, BriefingPeriod $period, CarbonInterface $date, User $creator): BriefingRecord
    {
        if (! $this->assignments->isBranchParticipant((int) $branch->id, $date)) {
            throw BriefingBundleUnavailableException::branchNotParticipant();
        }

        if ((int) $creator->branch_id !== (int) $branch->id || ! $this->assignments->isParticipant($creator, $date)) {
            throw BriefingBundleUnavailableException::actorNotParticipant();
        }

        $periodStart = $this->periods->start($period, $date);

        try {
            return Cache::lock($this->lockKey((int) $branch->id, $period, $periodStart), self::LOCK_SECONDS)
                ->block(self::LOCK_WAIT_SECONDS, fn (): BriefingRecord => DB::transaction(
                    fn (): BriefingRecord => $this->find((int) $branch->id, $period, $date)
                        ?? $this->establish($branch, $period, $date, $periodStart, $creator),
                ));
        } catch (UniqueConstraintViolationException $exception) {
            // The unique bundle key is the last line of defence if the cache lock was not shared
            // (e.g. two servers, or a cache store without atomic locks): the other request won,
            // so its bundle is the canonical one.
            return $this->find((int) $branch->id, $period, $date) ?? throw $exception;
        }
    }

    public function lockKey(int $branchId, BriefingPeriod $period, CarbonInterface $periodStart): string
    {
        return "briefing-bundle:{$branchId}:{$period->value}:{$periodStart->toDateString()}";
    }

    private function establish(Branch $branch, BriefingPeriod $period, CarbonInterface $date, CarbonImmutable $periodStart, User $creator): BriefingRecord
    {
        $tasks = $this->assignments->taskAssignmentsForBranch($branch, $period, $date);

        if ($tasks->isEmpty()) {
            throw BriefingBundleUnavailableException::nothingAssigned();
        }

        $periodEnd = $this->periods->end($period, $date);
        $legacy = $this->exactLegacyRecord((int) $branch->id, $period, $periodStart);

        $bundle = $legacy ?? new BriefingRecord([
            'user_id' => $creator->id,
            'branch_id' => $branch->id,
            'period' => $period->value,
            'record_date' => $periodStart->toDateString(),
        ]);

        $bundle->fill([
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'status' => BriefingBundleStatus::Draft->value,
            'scoring_method_version' => BriefingScoreResult::BUNDLE_METHOD_VERSION,
        ])->save();

        $this->snapshotRequirements($bundle, $tasks);

        $bundle->transitions()->create([
            'from_status' => null,
            'to_status' => BriefingBundleStatus::Draft->value,
            'actor_id' => $creator->id,
            'source' => $legacy !== null
                ? BriefingTransitionSource::SystemReconciliation->value
                : BriefingTransitionSource::UserAction->value,
            'metadata' => $legacy !== null
                ? ['adopted_legacy_record' => true, 'legacy_record_date' => $legacy->record_date->toDateString()]
                : null,
        ]);

        return $bundle;
    }

    /**
     * A legacy (ungrouped) row whose record_date is exactly the bundle's period_start already sits
     * on the business key, so it is adopted instead of colliding with it. Legacy rows elsewhere in
     * the window (e.g. weekly rows dated mid-week) are left for the grouping command.
     *
     * @throws BriefingBundleConflictException when several legacy rows sit on the key
     */
    private function exactLegacyRecord(int $branchId, BriefingPeriod $period, CarbonImmutable $periodStart): ?BriefingRecord
    {
        $candidates = BriefingRecord::query()
            ->where('branch_id', $branchId)
            ->where('period', $period->value)
            ->whereNull('status')
            ->whereDate('record_date', $periodStart)
            ->lockForUpdate()
            ->get();

        if ($candidates->count() > 1) {
            throw new BriefingBundleConflictException($candidates->modelKeys());
        }

        return $candidates->first();
    }

    /**
     * @param  Collection<int, BriefingTask>  $tasks
     */
    private function snapshotRequirements(BriefingRecord $bundle, Collection $tasks): void
    {
        $bundle->requirements()->createMany(
            $tasks->map(fn (BriefingTask $task): array => BriefingRecordRequirement::attributesFromTask($task))->all(),
        );
    }
}
