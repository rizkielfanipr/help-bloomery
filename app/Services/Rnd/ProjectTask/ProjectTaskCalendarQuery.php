<?php

namespace App\Services\Rnd\ProjectTask;

use App\Models\RndProjectTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;

/**
 * Read model shared by the global Kalender (`HasProjectTaskCalendar`) and the per-Project calendar
 * (`App\Livewire\RndProjectTaskCalendar`) — docs/rnd-project-checkpoint-calendar-prd.md §17.3.
 * The visible range and the Branch scope are always applied in SQL; filters only narrow it and
 * never widen authorization (§11.9). It holds no UI state and renders no markup.
 */
class ProjectTaskCalendarQuery
{
    /**
     * Monday before the 1st through the Sunday after month-end — every date the month grid shows.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function visibleRange(Carbon $month): array
    {
        return [
            $month->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY),
            $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY),
        ];
    }

    /**
     * The upper bound is an end-of-day datetime so the last grid day is included on every driver
     * (SQLite compares the stored `Y-m-d H:i:s` date cast as text).
     *
     * @param  array{project_id?: int|string, branch_id?: int|string, category?: string, status?: string, pic_id?: int|string, mine_only?: bool}  $filters
     * @return EloquentCollection<int, RndProjectTask>
     */
    public function tasks(User $user, Carbon $start, Carbon $end, array $filters = []): EloquentCollection
    {
        return RndProjectTask::query()
            ->with(['project', 'branches', 'assignments.user'])
            ->whereBetween('due_date', [$start->toDateString(), $end->copy()->endOfDay()->toDateTimeString()])
            ->when(filled($filters['project_id'] ?? null), fn (Builder $query) => $query->where('rnd_project_id', $filters['project_id']))
            ->when(filled($filters['category'] ?? null), fn (Builder $query) => $query->where('task_type', $filters['category']))
            ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(filled($filters['branch_id'] ?? null), fn (Builder $query) => $query->whereHas(
                'branches', fn (Builder $query) => $query->where('branches.id', $filters['branch_id'])
            ))
            ->when(filled($filters['pic_id'] ?? null), fn (Builder $query) => $query->whereHas(
                'assignments', fn (Builder $query) => $query->where('user_id', $filters['pic_id'])
            ))
            ->when($filters['mine_only'] ?? false, fn (Builder $query) => $query->whereHas(
                'assignments', fn (Builder $query) => $query->where('user_id', $user->id)
            ))
            ->tap(fn (Builder $query) => $this->applyBranchScope($query, $user))
            ->get();
    }

    /**
     * Users without blanket Branch access only see Tasks shared to one of their Branches or Tasks
     * they are a PIC of — mirrors `RndProjectTaskPolicy::view()` in SQL.
     *
     * @param  Builder<RndProjectTask>  $query
     */
    public function applyBranchScope(Builder $query, User $user): void
    {
        if ($user->canAccessAllBranches() || $user->can('view all branch rnd project tasks')) {
            return;
        }

        $query->where(function (Builder $query) use ($user): void {
            $query->whereHas('branches', fn (Builder $query) => $query->whereIn('branches.id', $user->accessibleBranchIds()))
                ->orWhereHas('assignments', fn (Builder $query) => $query->where('user_id', $user->id));
        });
    }
}
