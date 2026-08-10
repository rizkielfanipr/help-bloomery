<?php

namespace App\Filament\Casual\Pages;

use App\Models\SalesReport;
use App\Models\SalesReportShiftSubmission;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;

class SalesReportPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.sales-report-page';

    #[Url(as: 'reportDate')]
    public string $reportDate = '';

    public function mount(): void
    {
        if (! $this->reportDate) {
            $this->reportDate = now()->toDateString();
        }
    }

    public function getTitle(): string|Htmlable
    {
        return 'Sales Report';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function updatedReportDate(): void
    {
        // triggers reactive re-render for checkmarks
    }

    /** @return array<int, int> */
    public function getShiftNumbers(): array
    {
        return auth()->user()->branch?->salesShiftNumbers() ?? [1];
    }

    /** @return array<int, Carbon|null> */
    public function getShiftStatuses(): array
    {
        $user = auth()->user();

        if (! $user->branch_id) {
            return [1 => null];
        }

        $defaults = array_fill_keys($this->getShiftNumbers(), null);

        $report = SalesReport::where('branch_id', $user->branch_id)
            ->whereDate('report_date', $this->reportDate ?: now()->toDateString())
            ->first();

        if (! $report) {
            return $defaults;
        }

        return $report->shiftSubmissions
            ->mapWithKeys(fn (SalesReportShiftSubmission $submission) => [$submission->shift_number => $submission->submitted_at])
            ->union($defaults)
            ->sortKeys()
            ->all();
    }
}
