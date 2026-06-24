<?php

namespace App\Filament\Helpdesk\Resources\BriefingItems\Pages;

use App\Enums\BriefingPeriod;
use App\Filament\Helpdesk\Resources\BriefingItems\BriefingItemResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListBriefingItems extends ListRecords
{
    protected static string $resource = BriefingItemResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua'),
            'daily' => Tab::make('Harian')
                ->icon('heroicon-o-sun')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('briefing_records.period', BriefingPeriod::Daily->value)
                ),
            'weekly' => Tab::make('Mingguan')
                ->icon('heroicon-o-calendar-days')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('briefing_records.period', BriefingPeriod::Weekly->value)
                ),
            'monthly' => Tab::make('Bulanan')
                ->icon('heroicon-o-calendar')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('briefing_records.period', BriefingPeriod::Monthly->value)
                ),
        ];
    }
}
