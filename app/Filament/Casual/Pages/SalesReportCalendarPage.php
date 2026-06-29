<?php

namespace App\Filament\Casual\Pages;

use App\Models\SalesReport;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Computed;

class SalesReportCalendarPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.sales-report-calendar-page';

    public int $year;

    public int $month;

    public function mount(): void
    {
        $this->year = now()->year;
        $this->month = now()->month;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Kalender Sales';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function previousMonth(): void
    {
        $date = Carbon::create($this->year, $this->month)->subMonth();
        $this->year = $date->year;
        $this->month = $date->month;
        unset($this->calendarData);
    }

    public function nextMonth(): void
    {
        $date = Carbon::create($this->year, $this->month)->addMonth();
        $this->year = $date->year;
        $this->month = $date->month;
        unset($this->calendarData);
    }

    #[Computed]
    public function calendarData(): array
    {
        $branchId = auth()->user()->branch_id;
        $start = Carbon::create($this->year, $this->month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $today = Carbon::today();

        $reports = $branchId
            ? SalesReport::where('branch_id', $branchId)
                ->whereBetween('report_date', [$start, $end])
                ->with(['submittedBy'])
                ->get()
                ->keyBy(fn (SalesReport $r) => $r->report_date->toDateString())
            : collect();

        $days = [];
        $submittedCount = 0;
        $missedCount = 0;
        $current = $start->copy();

        while ($current <= $end) {
            $dateKey = $current->toDateString();
            $isPast = $current->lte($today);
            $report = $reports->get($dateKey);
            $isSubmitted = $report !== null;

            if ($isPast) {
                $isSubmitted ? $submittedCount++ : $missedCount++;
            }

            $days[] = [
                'date' => $dateKey,
                'day' => $current->day,
                'isToday' => $current->isToday(),
                'isFuture' => ! $isPast,
                'isSubmitted' => $isSubmitted,
                'submittedBy' => $report?->submittedBy?->name,
            ];

            $current->addDay();
        }

        $pastDaysCount = $today->between($start, $end)
            ? $today->diffInDays($start) + 1
            : ($today->gt($end) ? $end->daysInMonth : 0);

        return [
            'monthLabel' => Carbon::create($this->year, $this->month)->locale('id')->isoFormat('MMMM Y'),
            'startWeekday' => (int) $start->dayOfWeek,
            'days' => $days,
            'submittedCount' => $submittedCount,
            'missedCount' => $missedCount,
            'pastDaysCount' => $pastDaysCount,
        ];
    }
}
