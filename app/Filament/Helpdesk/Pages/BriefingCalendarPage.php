<?php

namespace App\Filament\Helpdesk\Pages;

use App\Enums\BriefingPeriod;
use App\Enums\BriefingReviewStatus;
use App\Models\Branch;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use App\Models\User;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;
use UnitEnum;

class BriefingCalendarPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|UnitEnum|null $navigationGroup = 'Human Resources';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Kalender Briefing';

    protected string $view = 'filament.helpdesk.pages.briefing-calendar-page';

    public int $year;

    public int $month;

    public string $viewPeriod = 'daily';

    public ?string $selectedDate = null;

    public function mount(): void
    {
        $this->year = now()->year;
        $this->month = now()->month;
        $this->selectedDate = now()->toDateString();
    }

    public function getTitle(): string
    {
        return 'Kalender Briefing';
    }

    public function previousMonth(): void
    {
        $date = Carbon::create($this->year, $this->month)->subMonth();
        $this->year = $date->year;
        $this->month = $date->month;
        $this->selectedDate = null;
        unset($this->calendarData);
        unset($this->selectedDetails);
    }

    public function nextMonth(): void
    {
        $date = Carbon::create($this->year, $this->month)->addMonth();
        $this->year = $date->year;
        $this->month = $date->month;
        $this->selectedDate = null;
        unset($this->calendarData);
        unset($this->selectedDetails);
    }

    public function setViewPeriod(string $period): void
    {
        $this->viewPeriod = $period;
        $this->selectedDate = null;
        unset($this->calendarData);
        unset($this->selectedDetails);
    }

    public function selectDate(string $date): void
    {
        $this->selectedDate = $this->selectedDate === $date ? null : $date;
        unset($this->selectedDetails);
    }

    #[Computed]
    public function calendarData(): array
    {
        $period = BriefingPeriod::from($this->viewPeriod);
        $start = Carbon::create($this->year, $this->month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $today = Carbon::today();

        $allStaff = User::role('casual_staff')
            ->whereNotNull('branch_id')
            ->select(['id', 'branch_id'])
            ->get();

        $allStaffIds = $allStaff->pluck('id');
        $totalStaff = $allStaff->count();

        $recordQuery = BriefingRecord::whereIn('user_id', $allStaffIds)
            ->where('period', $period->value)
            ->with('items');

        if ($period === BriefingPeriod::Monthly) {
            $recordQuery->whereYear('record_date', $this->year);
        } else {
            $recordQuery->whereBetween('record_date', [$start, $end]);
        }

        $records = $recordQuery->get();

        $recordIndex = [];
        foreach ($records as $record) {
            $recordIndex[$record->record_date->toDateString()][$record->user_id] = $record;
        }

        $days = [];
        $current = $start->copy();

        while ($current <= $end) {
            $dateKey = $current->toDateString();
            $isPast = $current->lte($today);
            $isMonday = $current->isMonday();
            $isFirstOfMonth = $current->day === 1;

            $isScheduled = match ($period) {
                BriefingPeriod::Daily => true,
                BriefingPeriod::Weekly => $isMonday,
                BriefingPeriod::Monthly => $isFirstOfMonth,
            };

            $stats = null;
            if ($isPast && $isScheduled && $totalStaff > 0) {
                $dayRecords = $recordIndex[$dateKey] ?? [];
                $submitted = count($dayRecords);
                $approved = 0;
                $pending = 0;
                $rejected = 0;

                foreach ($dayRecords as $record) {
                    $items = $record->items;
                    if ($items->isEmpty()) {
                        continue;
                    }
                    if ($items->contains(fn (BriefingItem $i) => $i->review_status === BriefingReviewStatus::Rejected)) {
                        $rejected++;
                    } elseif ($items->every(fn (BriefingItem $i) => $i->review_status === BriefingReviewStatus::Approved)) {
                        $approved++;
                    } elseif ($items->contains(fn (BriefingItem $i) => $i->review_status === BriefingReviewStatus::Pending)) {
                        $pending++;
                    }
                }

                $stats = [
                    'total' => $totalStaff,
                    'submitted' => $submitted,
                    'missing' => max(0, $totalStaff - $submitted),
                    'approved' => $approved,
                    'pending' => $pending,
                    'rejected' => $rejected,
                ];
            }

            $days[] = [
                'date' => $dateKey,
                'day' => $current->day,
                'isToday' => $current->isToday(),
                'isFuture' => ! $isPast,
                'isScheduled' => $isScheduled,
                'isSelected' => $this->selectedDate === $dateKey,
                'stats' => $stats,
            ];

            $current->addDay();
        }

        return [
            'monthLabel' => Carbon::create($this->year, $this->month)->locale('id')->isoFormat('MMMM Y'),
            'startWeekday' => (int) $start->dayOfWeek,
            'days' => $days,
            'totalStaff' => $totalStaff,
        ];
    }

    #[Computed]
    public function selectedDetails(): array
    {
        if (! $this->selectedDate) {
            return [];
        }

        $period = BriefingPeriod::from($this->viewPeriod);
        $date = Carbon::parse($this->selectedDate);

        $isScheduled = match ($period) {
            BriefingPeriod::Daily => true,
            BriefingPeriod::Weekly => $date->isMonday(),
            BriefingPeriod::Monthly => $date->day === 1,
        };

        if (! $isScheduled || $date->isFuture()) {
            return [];
        }

        $allStaff = User::role('casual_staff')
            ->whereNotNull('branch_id')
            ->select(['id', 'branch_id', 'name'])
            ->orderBy('name')
            ->get();

        $staffByBranch = $allStaff->groupBy('branch_id');

        $records = BriefingRecord::whereIn('user_id', $allStaff->pluck('id'))
            ->where('period', $period->value)
            ->whereDate('record_date', $this->selectedDate)
            ->with('items')
            ->get()
            ->keyBy('user_id');

        $branches = Branch::whereIn('id', $staffByBranch->keys())
            ->orderBy('name')
            ->get();

        $details = [];
        foreach ($branches as $branch) {
            $branchStaff = $staffByBranch[$branch->id] ?? collect();
            $users = [];
            $submitted = 0;
            $approved = 0;

            foreach ($branchStaff as $staff) {
                $record = $records->get($staff->id);
                $status = $this->getUserStatus($record);
                if ($status !== 'missing') {
                    $submitted++;
                }
                if ($status === 'approved') {
                    $approved++;
                }
                $users[] = [
                    'name' => $staff->name,
                    'status' => $status,
                    'submittedAt' => $record?->submitted_at?->format('H:i'),
                ];
            }

            $details[] = [
                'branch' => $branch->name,
                'total' => $branchStaff->count(),
                'submitted' => $submitted,
                'approved' => $approved,
                'users' => $users,
            ];
        }

        return $details;
    }

    private function getUserStatus(?BriefingRecord $record): string
    {
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
    }
}
