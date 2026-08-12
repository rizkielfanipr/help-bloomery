<?php

namespace App\Filament\Casual\Pages;

use App\Enums\StockCardStatus;
use App\Models\StockCard;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

class StockCardHistoryPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.stock-card-history-page';

    public ?int $expandedCardId = null;

    public int $loadedMonths = 3;

    public function getTitle(): string|Htmlable
    {
        return 'Riwayat Stock Card';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function toggleCard(int $id): void
    {
        $this->expandedCardId = $this->expandedCardId === $id ? null : $id;
    }

    public function loadMore(): void
    {
        $this->loadedMonths += 3;
    }

    public function stockCards(): Collection
    {
        $branchId = auth()->user()->branch_id;

        if (! $branchId) {
            return collect();
        }

        $since = Carbon::now()->subMonths($this->loadedMonths)->startOfMonth();

        return StockCard::where('branch_id', $branchId)
            ->where('report_date', '>=', $since)
            ->where('status', '!=', StockCardStatus::Draft->value)
            ->with(['entries', 'submittedBy', 'employees', 'approvals.actor'])
            ->orderBy('report_date', 'desc')
            ->get();
    }
}
