<?php

namespace App\Filament\Employee\Modules\StockCard\Pages;

use App\Models\StockCard;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;

class StockCardPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.employee.layouts.bare';

    protected string $view = 'filament.employee.stock-card.pages.stock-card-page';

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
        return 'Stock Card';
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
        // triggers reactive re-render for status badge
    }

    public function getDailyStatus(): ?StockCard
    {
        $user = auth()->user();

        if (! $user->branch_id) {
            return null;
        }

        return StockCard::where('branch_id', $user->branch_id)
            ->whereDate('report_date', $this->reportDate ?: now()->toDateString())
            ->first();
    }
}
