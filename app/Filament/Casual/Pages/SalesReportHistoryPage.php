<?php

namespace App\Filament\Casual\Pages;

use App\Models\SalesReport;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

class SalesReportHistoryPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.sales-report-history-page';

    public ?int $expandedReportId = null;

    public int $loadedMonths = 3;

    public function getTitle(): string|Htmlable
    {
        return 'Riwayat Sales';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function toggleReport(int $id): void
    {
        $this->expandedReportId = $this->expandedReportId === $id ? null : $id;
    }

    public function loadMore(): void
    {
        $this->loadedMonths += 3;
    }

    public function reports(): Collection
    {
        $branchId = auth()->user()->branch_id;

        if (! $branchId) {
            return collect();
        }

        $since = Carbon::now()->subMonths($this->loadedMonths)->startOfMonth();

        return SalesReport::where('branch_id', $branchId)
            ->where('report_date', '>=', $since)
            ->with(['entries', 'employees', 'shiftSubmissions.submittedBy', 'reconciliations'])
            ->orderBy('report_date', 'desc')
            ->get();
    }
}
