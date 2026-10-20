<?php

namespace App\Services\Kitchen;

use App\Enums\KitchenProductionScheduleStatus;
use App\Models\Branch;
use App\Models\KitchenProductionSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Read model for the Jadwal Produksi calendar (docs/kitchen-production-schedule-prd.md §13, §17,
 * §19). The visible range and the Branch scope are applied in SQL before anything is fetched;
 * filters only narrow that scope. Only header columns plus an item count are selected — never
 * `detail_snapshot` — and Branch names come from one eager load, so the query count stays flat.
 */
class ProductionScheduleCalendarQuery
{
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
     * @param  array{branch_id?: int|string|null, status?: string|null}  $filters
     * @return EloquentCollection<int, KitchenProductionSchedule>
     */
    public function schedules(User $user, CarbonImmutable $start, CarbonImmutable $end, array $filters = []): EloquentCollection
    {
        $status = KitchenProductionScheduleStatus::tryFrom((string) ($filters['status'] ?? ''));

        return KitchenProductionSchedule::query()
            ->select(['id', 'branch_id', 'production_date', 'status', 'updated_at'])
            ->visibleTo($user)
            ->whereDate('production_date', '>=', $start->toDateString())
            ->whereDate('production_date', '<=', $end->toDateString())
            ->when(filled($filters['branch_id'] ?? null), fn (Builder $query) => $query->where('branch_id', (int) $filters['branch_id']))
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status->value))
            ->withCount('items')
            ->with('branch:id,name')
            ->orderBy('production_date')
            ->orderBy('branch_id')
            ->orderBy('id')
            ->get();
    }

    /**
     * Branches the user may filter by. Inactive Branches stay listed when they have history.
     *
     * @return EloquentCollection<int, Branch>
     */
    public function filterBranches(User $user): EloquentCollection
    {
        return Branch::query()
            ->when(
                ! $user->canAccessAllBranches() && ! $user->can('view all branch kitchen production schedules'),
                fn (Builder $query) => $query->kitchenBrand(),
            )
            ->when(
                ! $user->canAccessAllBranches() && ! $user->can('view all branch kitchen production schedules'),
                fn (Builder $query) => $query->whereIn('id', $user->accessibleBranchIds()),
            )
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);
    }

    /**
     * Branches a new or edited schedule may target: active Branches the user can actually access.
     * Viewing every Branch never widens this list.
     *
     * @return EloquentCollection<int, Branch>
     */
    public function schedulableBranches(User $user): EloquentCollection
    {
        return Branch::query()
            ->where('is_active', true)
            ->when(
                ! $user->canAccessAllBranches(),
                fn (Builder $query) => $query
                    ->kitchenBrand()
                    ->whereIn('id', $user->accessibleBranchIds()),
            )
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
