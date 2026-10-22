<?php

namespace App\Filament\Helpdesk\Resources\SalesReports\Pages;

use App\Enums\SalesReportStatus;
use App\Filament\Helpdesk\Resources\SalesReports\Concerns\HasSalesReportSubNavigation;
use App\Filament\Helpdesk\Resources\SalesReports\SalesReportResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSalesReports extends ListRecords
{
    use HasSalesReportSubNavigation;

    protected static string $resource = SalesReportResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        // Finance approval is no longer a workflow step; legacy Finance Review rows stay reachable
        // until the status migration has converted them, then the tab disappears.
        $legacyFinanceCount = $this->statusCount(SalesReportStatus::PendingFinance);

        return [
            'all' => Tab::make('All'),
            'pending_supervisor' => Tab::make('Supervisor Review')
                ->badge(fn (): int => $this->statusCount(SalesReportStatus::PendingSupervisor))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', SalesReportStatus::PendingSupervisor->value)),
            ...($legacyFinanceCount > 0 ? ['pending_finance' => Tab::make(SalesReportStatus::PendingFinance->getLabel())
                ->badge($legacyFinanceCount)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', SalesReportStatus::PendingFinance->value))] : []),
            'completed' => Tab::make('Completed')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', SalesReportStatus::Completed->value)),
            'rejected_by_system' => Tab::make('Rejected by System')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', SalesReportStatus::RejectedBySystem->value)),
            'rejected' => Tab::make('Rejected')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', SalesReportStatus::Rejected->value)),
        ];
    }

    private function statusCount(SalesReportStatus $status): int
    {
        return SalesReportResource::getEloquentQuery()->where('status', $status->value)->count();
    }
}
