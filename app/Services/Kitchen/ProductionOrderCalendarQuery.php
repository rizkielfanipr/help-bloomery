<?php

namespace App\Services\Kitchen;

use App\Enums\EsbProductionOrderSyncStatus;
use App\Models\Branch;
use App\Models\BranchEsbCode;
use App\Models\EsbProductionOrder;
use App\Models\EsbProductionOrderSyncRun;
use App\Models\User;
use App\Services\Monitoring\MonitoringSanitizer;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Read model for the Jadwal Produksi calendar of ESB Production Orders
 * (docs/products/kitchen/production-schedule/prd.md §32–§34, §38). It reads local snapshots only — never ESB —
 * within the visible Monday–Sunday range and the Kitchen-Brand Branch scope, applied in SQL.
 * Details and materials are never loaded here; the query count stays flat regardless of the
 * number of orders.
 */
class ProductionOrderCalendarQuery
{
    public const STATE_OK = 'ok';

    public const STATE_NO_MAPPING = 'no_mapping';

    public const STATE_INCOMPLETE_MAPPING = 'incomplete_mapping';

    public const STATE_NEVER_SYNCED = 'never_synced';

    public const STATE_RUNNING = 'running';

    public const STATE_STALE = 'stale';

    public const STATE_PARTIAL = 'partial';

    public const STATE_FAILED = 'failed';

    public function __construct(
        private readonly ProductionOrderSyncPlanner $planner,
        private readonly MonitoringSanitizer $sanitizer,
    ) {}

    /**
     * Monday on/before the 1st through the Sunday on/after month-end — every date the grid shows.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function visibleRange(CarbonImmutable $month): array
    {
        return [
            $month->startOfMonth()->startOfWeek(CarbonImmutable::MONDAY),
            $month->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY)->startOfDay(),
        ];
    }

    /**
     * Kitchen-Brand Branches the user may filter by (prd.md §28.6, §34.2, K15) — never another Brand,
     * not even for all-Branch users. Inactive Branches stay listed when they have history.
     *
     * @return EloquentCollection<int, Branch>
     */
    public function filterBranches(User $user): EloquentCollection
    {
        return Branch::query()
            ->kitchenBrand()
            ->when(
                ! $user->canAccessAllBranches() && ! $user->can('view all branch kitchen production schedules'),
                fn (Builder $query) => $query->whereIn('id', $user->accessibleBranchIds()),
            )
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);
    }

    /**
     * @param  array{branch_id?: int|string|null, status?: string|null}  $filters
     * @return EloquentCollection<int, EsbProductionOrder>
     */
    public function orders(User $user, CarbonImmutable $start, CarbonImmutable $end, array $filters = []): EloquentCollection
    {
        return EsbProductionOrder::query()
            ->select(['id', 'branch_id', 'production_order_num', 'production_order_date', 'status_id', 'status_name', 'source_last_seen_at', 'details_synced_at'])
            ->visibleTo($user)
            ->whereDate('production_order_date', '>=', $start->toDateString())
            ->whereDate('production_order_date', '<=', $end->toDateString())
            ->when(filled($filters['branch_id'] ?? null), fn (Builder $query) => $query->where('branch_id', (int) $filters['branch_id']))
            ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query->where('status_id', (string) $filters['status']))
            ->withCount('details')
            ->with('branch:id,name')
            ->orderBy('production_order_date')
            ->orderBy('branch_id')
            ->orderBy('production_order_num')
            ->get();
    }

    /**
     * ESB statuses present in the user's Branch scope, labelled from the snapshot.
     *
     * @return array<string, string> status ID => label
     */
    public function statusOptions(User $user): array
    {
        return EsbProductionOrder::query()
            ->visibleTo($user)
            ->whereNotNull('status_id')
            ->select(['status_id', 'status_name'])
            ->distinct()
            ->get()
            ->unique('status_id')
            ->sortBy(fn (EsbProductionOrder $order): string => mb_strtolower($order->label()))
            ->mapWithKeys(fn (EsbProductionOrder $order): array => [(string) $order->status_id => $order->label()])
            ->all();
    }

    /**
     * Sync state per Kitchen Branch in scope (docs/products/kitchen/production-schedule/prd.md §34.4), grouped
     * by state for the banner, plus the oldest "last synced" time among synced mappings.
     *
     * @return array{
     *     groups: array<string, list<string>>,
     *     issues: array<string, list<array{branch: string, message: string, errorCode: ?string, finishedAt: ?CarbonInterface}>>,
     *     lastSyncedAt: ?CarbonInterface
     * }
     */
    public function syncSummary(User $user, ?int $branchId = null): array
    {
        // Kitchen-Brand Branches only, for every user (prd.md §28.6, K15).
        $branches = Branch::query()
            ->kitchenBrand()
            ->where('is_active', true)
            ->when(
                ! $user->canAccessAllBranches() && ! $user->can('view all branch kitchen production schedules'),
                fn (Builder $query) => $query->whereIn('id', $user->accessibleBranchIds()),
            )
            ->when($branchId !== null, fn (Builder $query) => $query->whereKey($branchId))
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($branches->isEmpty()) {
            return ['groups' => [], 'issues' => [], 'lastSyncedAt' => null];
        }

        $mappings = BranchEsbCode::query()->whereIn('branch_id', $branches->modelKeys())->get();
        $eligible = $mappings->filter(fn (BranchEsbCode $mapping): bool => $this->planner->incompleteReason($mapping) === null);
        $latestRuns = $this->latestRuns($eligible->modelKeys());
        $lastCompleted = EsbProductionOrderSyncRun::query()
            ->whereIn('branch_esb_code_id', $eligible->modelKeys())
            ->whereNotNull('list_completed_at')
            ->groupBy('branch_esb_code_id')
            ->selectRaw('branch_esb_code_id, max(list_completed_at) as last_completed_at')
            ->pluck('last_completed_at', 'branch_esb_code_id')
            ->map(fn (mixed $value): CarbonImmutable => CarbonImmutable::parse((string) $value));

        $staleBefore = now()->subMinutes((int) config('kitchen.production_orders.stale_after_minutes', 180));
        $groups = [];
        $issues = [];

        foreach ($branches as $branch) {
            $branchMappings = $mappings->where('branch_id', $branch->id);
            $branchEligible = $eligible->where('branch_id', $branch->id);

            $state = match (true) {
                $branchMappings->where('is_active', true)->isEmpty() => self::STATE_NO_MAPPING,
                $branchEligible->isEmpty() => self::STATE_INCOMPLETE_MAPPING,
                default => $this->worstState($branchEligible->map(fn (BranchEsbCode $mapping): string => $this->mappingState(
                    $latestRuns->get($mapping->id),
                    $lastCompleted->get($mapping->id),
                    $staleBefore,
                ))),
            };

            $groups[$state][] = $branch->name;

            if (in_array($state, [self::STATE_FAILED, self::STATE_PARTIAL], true)) {
                $relevantStatus = $state === self::STATE_FAILED
                    ? EsbProductionOrderSyncStatus::Failed
                    : EsbProductionOrderSyncStatus::Partial;
                $run = $branchEligible
                    ->map(fn (BranchEsbCode $mapping): ?EsbProductionOrderSyncRun => $latestRuns->get($mapping->id))
                    ->filter(fn (?EsbProductionOrderSyncRun $candidate): bool => $candidate?->status === $relevantStatus)
                    ->sortByDesc('id')
                    ->first();

                $issues[$state][] = [
                    'branch' => $branch->name,
                    'message' => $this->sanitizer->message($run?->error_summary)
                        ?? ($state === self::STATE_FAILED
                            ? 'ESB menolak atau gagal memproses permintaan sinkronisasi.'
                            : 'Sebagian detail Production Order gagal diambil dari ESB.'),
                    'errorCode' => filled($run?->error_code) ? (string) $run->error_code : null,
                    'finishedAt' => $run?->finished_at,
                ];
            }
        }

        return [
            'groups' => $groups,
            'issues' => $issues,
            'lastSyncedAt' => $lastCompleted->isEmpty() ? null : $lastCompleted->min(),
        ];
    }

    /**
     * @param  list<int>  $mappingIds
     * @return Collection<int, EsbProductionOrderSyncRun>
     */
    private function latestRuns(array $mappingIds): Collection
    {
        if ($mappingIds === []) {
            return collect();
        }

        $ids = EsbProductionOrderSyncRun::query()
            ->whereIn('branch_esb_code_id', $mappingIds)
            ->groupBy('branch_esb_code_id')
            ->selectRaw('max(id) as id')
            ->pluck('id');

        return EsbProductionOrderSyncRun::query()
            ->whereKey($ids)
            ->get(['id', 'branch_esb_code_id', 'status', 'error_code', 'error_summary', 'finished_at'])
            ->keyBy('branch_esb_code_id');
    }

    private function mappingState(?EsbProductionOrderSyncRun $latest, ?CarbonInterface $lastCompleted, CarbonInterface $staleBefore): string
    {
        // A queued or running sync is reported as running first, so the page keeps refreshing (§39.2).
        return match (true) {
            $latest === null => self::STATE_NEVER_SYNCED,
            $latest->status->isActive() => self::STATE_RUNNING,
            $latest->status === EsbProductionOrderSyncStatus::Failed => self::STATE_FAILED,
            $latest->status === EsbProductionOrderSyncStatus::Partial => self::STATE_PARTIAL,
            $lastCompleted === null => self::STATE_NEVER_SYNCED,
            $lastCompleted->lt($staleBefore) => self::STATE_STALE,
            default => self::STATE_OK,
        };
    }

    /**
     * @param  Collection<int, string>  $states
     */
    private function worstState(Collection $states): string
    {
        foreach ([self::STATE_RUNNING, self::STATE_FAILED, self::STATE_PARTIAL, self::STATE_STALE, self::STATE_NEVER_SYNCED] as $state) {
            if ($states->contains($state)) {
                return $state;
            }
        }

        return self::STATE_OK;
    }
}
