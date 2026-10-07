<?php

namespace App\Livewire;

use App\Filament\Helpdesk\Concerns\AppliesProjectTaskTemplates;
use App\Filament\Helpdesk\Concerns\InteractsWithProjectTasks;
use App\Models\RndProject;
use App\Models\RndProjectTask;
use App\Services\Rnd\ProjectTask\ProjectTaskCalendarQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Kalender & Task" section of the Project detail page (docs/rnd-project-checkpoint-calendar-prd.md
 * §7.1, §11, §17.4) — the operational workspace for one Project's Tasks. A dedicated child
 * component so `ViewProject` stays thin: it owns the month state and range query, and reuses the
 * shared Task workflow (`InteractsWithProjectTasks`) and the apply-template wizard with the Project
 * locked server-side. The Project ID is `#[Locked]` and every Task lookup is scoped to it, so the
 * browser can never redirect a mutation to another Project.
 */
class RndProjectTaskCalendar extends Component
{
    use AppliesProjectTaskTemplates;
    use InteractsWithProjectTasks;

    #[Locked]
    public int $projectId;

    public string $calendarMonth = '';

    public function mount(int $projectId): void
    {
        abort_unless(auth()->user()?->can('viewAny', RndProjectTask::class), 403);

        $this->projectId = $projectId;
        $this->calendarMonth = today()->format('Y-m');
    }

    /**
     * Re-read on every request (archived Projects included) so a Project archived in another tab
     * immediately becomes read-only here.
     */
    #[Computed]
    public function project(): RndProject
    {
        return RndProject::withTrashed()->findOrFail($this->projectId);
    }

    public function previousMonth(): void
    {
        $this->calendarMonth = $this->selectedMonth()->subMonthNoOverflow()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->calendarMonth = $this->selectedMonth()->addMonthNoOverflow()->format('Y-m');
    }

    public function currentMonth(): void
    {
        $this->calendarMonth = today()->format('Y-m');
    }

    /**
     * Every date of the visible grid with that date's Tasks — the single dataset behind both the
     * desktop month grid and the mobile agenda.
     *
     * @return list<array{date: Carbon, isCurrentMonth: bool, isToday: bool, isRelease: bool, tasks: list<RndProjectTask>}>
     */
    public function calendarDays(): array
    {
        $month = $this->selectedMonth();
        $calendarQuery = app(ProjectTaskCalendarQuery::class);
        [$calendarStart, $calendarEnd] = $calendarQuery->visibleRange($month);
        $releaseDate = $this->project->end_date;

        $tasksByDate = $calendarQuery
            ->tasks(auth()->user(), $calendarStart, $calendarEnd, ['project_id' => $this->projectId])
            ->sortBy(fn (RndProjectTask $task): string => $task->due_date->format('Y-m-d').'|'.str_pad((string) $task->id, 10, '0', STR_PAD_LEFT))
            ->groupBy(fn (RndProjectTask $task): string => $task->due_date->toDateString());

        $days = [];

        for ($date = $calendarStart->copy(); $date->lte($calendarEnd); $date->addDay()) {
            $days[] = [
                'date' => $date->copy(),
                'isCurrentMonth' => $date->month === $month->month,
                'isToday' => $date->isToday(),
                'isRelease' => $date->isSameDay($releaseDate),
                'tasks' => $tasksByDate->get($date->toDateString(), collect())->values()->all(),
            ];
        }

        return $days;
    }

    public function isReadOnly(): bool
    {
        return $this->project->trashed();
    }

    protected function lockedTaskProject(): ?RndProject
    {
        return $this->project;
    }

    /** @return Builder<RndProjectTask> */
    protected function projectTaskQuery(): Builder
    {
        return RndProjectTask::query()->where('rnd_project_id', $this->projectId);
    }

    /**
     * Besides the shared forms, the apply wizard has one picker per checkpoint row
     * (`applyRows.{index}.branch_rows`).
     */
    protected function allowsBranchRowPath(string $path): bool
    {
        if (in_array($path, ['taskBranchRows', 'copyBranchRows'], true)) {
            return true;
        }

        return preg_match('/^applyRows\.(\d+)\.branch_rows$/', $path, $matches) === 1
            && isset($this->applyRows[(int) $matches[1]]);
    }

    protected function copiedProjectTask(RndProjectTask $copy): void
    {
        $this->calendarMonth = $copy->due_date->format('Y-m');
    }

    /** @param  EloquentCollection<int, RndProjectTask>  $tasks */
    protected function appliedProjectTaskTemplate(EloquentCollection $tasks): void
    {
        $firstDueDate = $tasks->min('due_date');

        if ($firstDueDate !== null) {
            $this->calendarMonth = Carbon::parse($firstDueDate)->format('Y-m');
        }
    }

    private function selectedMonth(): Carbon
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->calendarMonth) !== 1) {
            $this->calendarMonth = today()->format('Y-m');
        }

        return Carbon::createFromFormat('Y-m-d', $this->calendarMonth.'-01')->startOfDay();
    }

    public function render(): View
    {
        $days = $this->calendarDays();
        $selectedMonth = $this->selectedMonth();

        return view('livewire.rnd-project-task-calendar', [
            'monthLabel' => $selectedMonth->translatedFormat('F Y'),
            'weeks' => array_chunk($days, 7),
            'agendaDays' => array_values(array_filter($days, fn (array $day): bool => $day['tasks'] !== [] || $day['isRelease'])),
            'canCreateTask' => $this->canCreateTask(),
            'canApplyTemplate' => $this->canApplyTemplate(),
        ]);
    }
}
