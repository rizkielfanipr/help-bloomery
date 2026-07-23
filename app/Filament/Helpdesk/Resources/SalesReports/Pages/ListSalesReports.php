<?php

namespace App\Filament\Helpdesk\Resources\SalesReports\Pages;

use App\Enums\SalesReportStatus;
use App\Filament\Helpdesk\Resources\SalesReports\SalesReportResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSalesReports extends ListRecords
{
    protected static string $resource = SalesReportResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua'),
            'pending_supervisor' => Tab::make('Menunggu SPV')
                ->badge(fn (): int => $this->statusCount(SalesReportStatus::PendingSupervisor))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', SalesReportStatus::PendingSupervisor->value)),
            'pending_finance' => Tab::make('Menunggu Finance')
                ->badge(fn (): int => $this->statusCount(SalesReportStatus::PendingFinance))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', SalesReportStatus::PendingFinance->value)),
            'rejected' => Tab::make('Ditolak')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                    SalesReportStatus::RejectedBySupervisor->value,
                    SalesReportStatus::RejectedByFinance->value,
                ])),
            'completed' => Tab::make('Selesai')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', SalesReportStatus::Completed->value)),
        ];
    }

    private function statusCount(SalesReportStatus $status): int
    {
        return SalesReportResource::getEloquentQuery()->where('status', $status->value)->count();
    }
}
