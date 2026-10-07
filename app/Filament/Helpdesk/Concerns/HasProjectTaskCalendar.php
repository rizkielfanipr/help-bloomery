<?php

namespace App\Filament\Helpdesk\Concerns;

use App\Models\RndProject;
use App\Models\RndProjectTask;
use App\Models\RndProjectTaskAssignment;
use App\Models\User;
use App\Services\Rnd\ProjectTask\ProjectTaskCalendarQuery;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;

/**
 * Merged Kalender mode for the Project index (docs/rnd-project-task-calendar-prd.md §14, Phase 2).
 * Mirrors the existing release-calendar month-grid mechanics in `ListProjects::calendar()`, and
 * additionally overlays each week with that same month's project release dates (`taskCalendar()`'s
 * `projects` segments), so a single toggle shows both release dates and Task deadlines. The task
 * query itself stays scoped to the visible date range and to the user's branch access in SQL, per
 * §19 ("Kalender hanya mengambil data dalam rentang tanggal yang sedang ditampilkan") — the legacy
 * `ListProjects::calendar()` method (used standalone for users without Task permissions) keeps its
 * own looser, in-memory-filtered behavior unchanged.
 *
 * The Task workflow itself (form, detail, follow-up, review, copy) lives in
 * `InteractsWithProjectTasks`, shared with the per-Project calendar.
 */
trait HasProjectTaskCalendar
{
    use InteractsWithProjectTasks;

    public string $taskCalendarMonth = '';

    public string $taskFilterProjectId = '';

    public string $taskFilterBranchId = '';

    public string $taskFilterCategory = '';

    public string $taskFilterStatus = '';

    public string $taskFilterPicId = '';

    public bool $taskFilterMineOnly = false;

    public function showProjectTasks(): void
    {
        $this->projectView = 'tasks';
        $this->ensureTaskCalendarMonthIsSet();
    }

    public function previousTaskCalendarMonth(): void
    {
        $this->taskCalendarMonth = $this->selectedTaskCalendarMonth()->subMonthNoOverflow()->format('Y-m');
    }

    public function nextTaskCalendarMonth(): void
    {
        $this->taskCalendarMonth = $this->selectedTaskCalendarMonth()->addMonthNoOverflow()->format('Y-m');
    }

    public function currentTaskCalendarMonth(): void
    {
        $this->taskCalendarMonth = today()->format('Y-m');
    }

    public function resetTaskFilters(): void
    {
        $this->taskFilterProjectId = '';
        $this->taskFilterBranchId = '';
        $this->taskFilterCategory = '';
        $this->taskFilterStatus = '';
        $this->taskFilterPicId = '';
        $this->taskFilterMineOnly = false;
    }

    /** @return EloquentCollection<int, RndProjectTask> */
    public function taskCalendarTasks(): EloquentCollection
    {
        $calendarQuery = app(ProjectTaskCalendarQuery::class);
        [$calendarStart, $calendarEnd] = $calendarQuery->visibleRange($this->selectedTaskCalendarMonth());

        return $calendarQuery->tasks(auth()->user(), $calendarStart, $calendarEnd, [
            'project_id' => $this->taskFilterProjectId,
            'category' => $this->taskFilterCategory,
            'status' => $this->taskFilterStatus,
            'branch_id' => $this->taskFilterBranchId,
            'pic_id' => $this->taskFilterPicId,
            'mine_only' => $this->taskFilterMineOnly,
        ]);
    }

    /**
     * @return array{monthLabel: string, weeks: array<int, array{dates: array<int, array{date: Carbon, isCurrentMonth: bool, isToday: bool}>, tasks: array<int, array{task: RndProjectTask, dayColumn: int}>, projects: array<int, array{project: RndProject, dayColumn: int}>}>}
     */
    public function taskCalendar(): array
    {
        $month = $this->selectedTaskCalendarMonth();
        [$calendarStart, $calendarEnd] = app(ProjectTaskCalendarQuery::class)->visibleRange($month);
        $tasks = $this->taskCalendarTasks();
        $releaseProjects = $this->projects()
            ->filter(fn (RndProject $project): bool => $project->end_date->betweenIncluded($calendarStart, $calendarEnd));
        $weeks = [];

        for ($weekStart = $calendarStart->copy(); $weekStart->lte($calendarEnd); $weekStart->addWeek()) {
            $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
            $dates = [];

            for ($date = $weekStart->copy(); $date->lte($weekEnd); $date->addDay()) {
                $dates[] = [
                    'date' => $date->copy(),
                    'isCurrentMonth' => $date->month === $month->month,
                    'isToday' => $date->isToday(),
                ];
            }

            $segments = $tasks
                ->filter(fn (RndProjectTask $task): bool => $task->due_date->betweenIncluded($weekStart, $weekEnd))
                ->sortBy(fn (RndProjectTask $task): string => $task->due_date->format('Y-m-d').'|'.str_pad((string) $task->id, 10, '0', STR_PAD_LEFT))
                ->map(fn (RndProjectTask $task): array => [
                    'task' => $task,
                    'dayColumn' => (int) $weekStart->diffInDays($task->due_date) + 1,
                ])
                ->values()
                ->all();

            $releaseSegments = $releaseProjects
                ->filter(fn (RndProject $project): bool => $project->end_date->betweenIncluded($weekStart, $weekEnd))
                ->sortBy(fn (RndProject $project): string => $project->end_date->format('Y-m-d').'|'.str_pad((string) $project->id, 10, '0', STR_PAD_LEFT))
                ->map(fn (RndProject $project): array => [
                    'project' => $project,
                    'dayColumn' => (int) $weekStart->diffInDays($project->end_date) + 1,
                ])
                ->values()
                ->all();

            $weeks[] = ['dates' => $dates, 'tasks' => $segments, 'projects' => $releaseSegments];
        }

        return [
            'monthLabel' => $month->translatedFormat('F Y'),
            'weeks' => $weeks,
        ];
    }

    private function ensureTaskCalendarMonthIsSet(): void
    {
        if ($this->taskCalendarMonth === '') {
            $this->taskCalendarMonth = today()->format('Y-m');
        }
    }

    private function selectedTaskCalendarMonth(): Carbon
    {
        $this->ensureTaskCalendarMonthIsSet();

        return Carbon::createFromFormat('Y-m-d', $this->taskCalendarMonth.'-01')->startOfDay();
    }

    /** @return EloquentCollection<int, User> */
    public function taskFilterPics(): EloquentCollection
    {
        return User::query()
            ->whereHas('projectTaskAssignments')
            ->orderBy('username')
            ->orderBy('name')
            ->get(['id', 'name', 'username']);
    }

    /**
     * "Tugas Saya" sidebar next to the merged Kalender (docs/rnd-project-task-calendar-prd.md §15)
     * — the current user's own non-Cancelled assignments, ordered overdue first, then deadline
     * today, then Urgent/High priority, then nearest deadline. Mirrors the Dashboard's "Tugas yang
     * Perlu Ditindaklanjuti" ordering but also surfaces Submitted assignments awaiting review.
     *
     * @return EloquentCollection<int, RndProjectTaskAssignment>
     */
    public function myOpenTaskAssignments(): EloquentCollection
    {
        return RndProjectTaskAssignment::query()
            ->with(['task.project', 'branch'])
            ->where('user_id', auth()->id())
            ->whereIn('status', ['assigned', 'in_progress', 'submitted', 'revision_required'])
            ->get()
            ->sortBy(fn (RndProjectTaskAssignment $assignment): string => sprintf(
                '%d|%s',
                match (true) {
                    $assignment->task->isOverdue() => 0,
                    $assignment->task->due_date->isToday() => 1,
                    $assignment->task->priority->isUrgentOrHigh() => 2,
                    default => 3,
                },
                $assignment->task->due_date->format('Y-m-d'),
            ))
            ->values();
    }
}
