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
    public static function canAccess(): bool
    {
        return auth()->user()?->can('view briefing records') ?? false;
    }

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

        $allStaff = User::select(['id', 'branch_id'])->whereNotNull('branch_id')->role('STORE_STAFF')->get();
        $allStaffIds = $allStaff->pluck('id');

        $branchGroups = $allStaff->groupBy('branch_id');
        $totalBranches = $branchGroups->count();

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
            if ($isPast && $isScheduled && $totalBranches > 0) {
                $dayRecords = $recordIndex[$dateKey] ?? [];
                $submittedBranches = 0;
                $approvedBranches = 0;

                foreach ($branchGroups as $branchStaff) {
                    $branchStaffIds = $branchStaff->pluck('id')->toArray();
                    $branchSubmitted = array_intersect_key($dayRecords, array_flip($branchStaffIds));

                    if (count($branchSubmitted) >= count($branchStaffIds)) {
                        $submittedBranches++;

                        $allApproved = true;
                        foreach ($branchSubmitted as $record) {
                            if ($record->items->isEmpty() || ! $record->items->every(fn (BriefingItem $i) => $i->review_status === BriefingReviewStatus::Approved)) {
                                $allApproved = false;
                                break;
                            }
                        }
                        if ($allApproved) {
                            $approvedBranches++;
                        }
                    }
                }

                $stats = [
                    'total' => $totalBranches,
                    'submitted' => $submittedBranches,
                    'missing' => max(0, $totalBranches - $submittedBranches),
                    'approved' => $approvedBranches,
                    'pending' => 0,
                    'rejected' => 0,
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
            'totalBranches' => $totalBranches,
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

        $branches = Branch::with(['users' => fn ($q) => $q->role('STORE_STAFF')->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->filter(fn (Branch $b) => $b->users->isNotEmpty());

        $allStaffIds = $branches->flatMap(fn (Branch $b) => $b->users->pluck('id'));

        $records = BriefingRecord::whereIn('user_id', $allStaffIds)
            ->where('period', $period->value)
            ->whereDate('record_date', $this->selectedDate)
            ->with('items')
            ->get()
            ->keyBy('user_id');

        $details = [];

        foreach ($branches as $branch) {
            $submitted = 0;
            $approved = 0;
            $submittedTimes = [];
            $submittedUsers = [];

            foreach ($branch->users as $staff) {
                $record = $records->get($staff->id);
                $status = $this->getUserStatus($record);
                if ($status !== 'missing') {
                    $submitted++;
                    if ($record?->submitted_at) {
                        $submittedTimes[] = $record->submitted_at;
                    }
                    $submittedUsers[] = [
                        'name' => $staff->name,
                        'status' => $status,
                        'submittedAt' => $record?->submitted_at?->format('H:i'),
                    ];
                }
                if ($status === 'approved') {
                    $approved++;
                }
            }

            $details[] = [
                'branch' => $branch->name,
                'total' => $branch->users->count(),
                'submitted' => $submitted,
                'approved' => $approved,
                'firstSubmit' => $submittedTimes ? min($submittedTimes)->format('H:i') : null,
                'lastSubmit' => $submittedTimes ? max($submittedTimes)->format('H:i') : null,
                'users' => $submittedUsers,
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
        if ($items->contains(fn (BriefingItem $i) => $i->review_status?->isAwaitingSupervisorReview() ?? false)) {
            return 'supervisor_review';
        }

        return 'submitted';
    }
}
