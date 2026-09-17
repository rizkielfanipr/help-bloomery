<?php

namespace App\Filament\Helpdesk\Pages;

use App\Models\Branch;
use App\Services\SalesReportScoreCalculator;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

class SalesReportScoresPage extends Page
{
    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $title = 'Sales Report Scores';

    protected string $view = 'filament.helpdesk.pages.sales-report-scores-page';

    public string $month = '';

    public string $branchFilter = '';

    #[Locked]
    public ?int $detailBranchId = null;

    #[Locked]
    public ?int $settingsBranchId = null;

    public string $assessmentStart = '';

    public array $excludedDates = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view sales report scores') ?? false;
    }

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
    }

    private function branchesQuery(): Builder
    {
        abort_unless(static::canAccess(), 403);
        $query = Branch::query()->orderBy('name');
        if (! auth()->user()->canAccessAllBranches()) {
            $query->whereIn('id', auth()->user()->accessibleBranchIds());
        }

        return $query;
    }

    #[Computed]
    public function branches(): Collection
    {
        return $this->branchesQuery()->get();
    }

    #[Computed]
    public function scores(): array
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->month)) {
            return [];
        }
        $branches = $this->branchesQuery()->when($this->branchFilter !== '', fn (Builder $query): Builder => $query->whereKey($this->branchFilter))->get();

        return app(SalesReportScoreCalculator::class)->calculate($branches, $this->month);
    }

    public function showDetail(int $branchId): void
    {
        $this->branchesQuery()->findOrFail($branchId);
        $this->settingsBranchId = null;
        $this->detailBranchId = $branchId;
    }

    public function closeDetail(): void
    {
        $this->detailBranchId = null;
    }

    public function updatedMonth(): void
    {
        $this->closeDetail();
    }

    public function updatedBranchFilter(): void
    {
        $this->closeDetail();
    }

    public function openSettings(int $branchId): void
    {
        abort_unless(auth()->user()->can('edit sales report assessment settings'), 403);
        $branch = $this->branchesQuery()->findOrFail($branchId);
        $this->detailBranchId = null;
        $this->settingsBranchId = $branchId;
        $this->assessmentStart = $branch->sales_assessment_started_at?->toDateString() ?? '';
        $this->excludedDates = $branch->sales_assessment_excluded_dates ?? [];
        $this->resetValidation();
    }

    public function addExcludedDate(): void
    {
        $this->excludedDates[] = ['date' => '', 'reason' => ''];
    }

    public function removeExcludedDate(int $index): void
    {
        unset($this->excludedDates[$index]);
        $this->excludedDates = array_values($this->excludedDates);
    }

    public function closeSettings(): void
    {
        $this->settingsBranchId = null;
    }

    public function saveSettings(): void
    {
        abort_unless(auth()->user()->can('edit sales report assessment settings'), 403);
        $branch = $this->branchesQuery()->findOrFail($this->settingsBranchId);
        $this->validate([
            'assessmentStart' => ['required', 'date_format:Y-m-d'],
            'excludedDates' => ['array', 'max:366'],
            'excludedDates.*.date' => ['required', 'date_format:Y-m-d', 'distinct'],
            'excludedDates.*.reason' => ['required', 'string', 'max:255'],
        ]);
        $branch->update(['sales_assessment_started_at' => $this->assessmentStart, 'sales_assessment_excluded_dates' => $this->excludedDates]);
        $this->settingsBranchId = null;
        unset($this->scores, $this->branches);
        Notification::make()->title('Pengaturan penilaian disimpan')->success()->send();
    }
}
