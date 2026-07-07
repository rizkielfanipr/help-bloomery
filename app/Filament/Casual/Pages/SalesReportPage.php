<?php

namespace App\Filament\Casual\Pages;

use App\Models\SalesReport;
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

    public function getShiftStatus(): array
    {
        $user = auth()->user();

        if (! $user->branch_id) {
            return ['shift1' => null, 'shift2' => null];
        }

        $report = SalesReport::where('branch_id', $user->branch_id)
            ->whereDate('report_date', $this->reportDate ?: now()->toDateString())
            ->first();

        return [
            'shift1' => $report?->shift_1_submitted_at,
            'shift2' => $report?->shift_2_submitted_at,
        ];
    }
}
