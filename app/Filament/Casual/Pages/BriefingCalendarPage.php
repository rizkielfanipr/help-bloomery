<?php

namespace App\Filament\Casual\Pages;

use App\Enums\BriefingPeriod;
use App\Enums\BriefingReviewStatus;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Computed;

class BriefingCalendarPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.briefing-calendar-page';

    public int $year;

    public int $month;

    public function mount(): void
    {
        $this->year = now()->year;
        $this->month = now()->month;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Kalender Briefing';
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
        $userId = auth()->id();
        $start = Carbon::create($this->year, $this->month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $today = Carbon::today();

        $dailyRecords = BriefingRecord::where('user_id', $userId)
            ->where('period', BriefingPeriod::Daily->value)
            ->whereBetween('record_date', [$start, $end])
            ->with('items')
            ->get()
            ->keyBy(fn (BriefingRecord $r) => $r->record_date->toDateString());

        $weeklyRecords = BriefingRecord::where('user_id', $userId)
            ->where('period', BriefingPeriod::Weekly->value)
            ->whereBetween('record_date', [$start, $end])
            ->with('items')
            ->get()
            ->keyBy(fn (BriefingRecord $r) => $r->record_date->toDateString());

        $monthlyRecord = BriefingRecord::where('user_id', $userId)
            ->where('period', BriefingPeriod::Monthly->value)
            ->whereDate('record_date', $start->toDateString())
            ->with('items')
            ->first();

        $getStatus = function (?BriefingRecord $record): string {
            if (! $record || $record->items->isEmpty()) {
                return 'missing';
            }
            $items = $record->items;
            if ($items->contains(fn (BriefingItem $i) => $i->review_status === BriefingReviewStatus::Rejected)) {
                return 'rejected';
            }
            if ($items->every(fn (BriefingItem $i) => $i->review_status === BriefingReviewStatus::Approved)) {
                return 'approved';
            }
            if ($items->contains(fn (BriefingItem $i) => $i->review_status === BriefingReviewStatus::Pending)) {
                return 'pending';
            }

            return 'submitted';
        };

        $days = [];
        $missedDailyCount = 0;
        $missedWeeklyCount = 0;
        $current = $start->copy();

        while ($current <= $end) {
            $dateKey = $current->toDateString();
            $isPast = $current->lte($today);
            $isMonday = $current->isMonday();
            $isFirstOfMonth = $current->day === 1;

            $dailyStatus = $isPast ? $getStatus($dailyRecords->get($dateKey)) : null;
            $weeklyStatus = $isMonday ? ($isPast ? $getStatus($weeklyRecords->get($dateKey)) : 'upcoming') : null;
            $monthlyStatus = $isFirstOfMonth ? ($isPast ? $getStatus($monthlyRecord) : 'upcoming') : null;

            if ($isPast && $dailyStatus === 'missing') {
                $missedDailyCount++;
            }
            if ($isPast && $isMonday && $weeklyStatus === 'missing') {
                $missedWeeklyCount++;
            }

            $days[] = [
                'date' => $dateKey,
                'day' => $current->day,
                'isToday' => $current->isToday(),
                'isFuture' => ! $isPast,
                'isMonday' => $isMonday,
                'isFirstOfMonth' => $isFirstOfMonth,
                'dailyStatus' => $dailyStatus,
                'weeklyStatus' => $weeklyStatus,
                'monthlyStatus' => $monthlyStatus,
            ];

            $current->addDay();
        }

        return [
            'monthLabel' => Carbon::create($this->year, $this->month)->locale('id')->isoFormat('MMMM Y'),
            'startWeekday' => (int) $start->dayOfWeek,
            'days' => $days,
            'missedDailyCount' => $missedDailyCount,
            'missedWeeklyCount' => $missedWeeklyCount,
            'monthlyStatus' => $start->lte($today) ? $getStatus($monthlyRecord) : 'upcoming',
        ];
    }
}
