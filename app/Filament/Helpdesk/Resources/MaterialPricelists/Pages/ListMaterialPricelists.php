<?php

namespace App\Filament\Helpdesk\Resources\MaterialPricelists\Pages;

use App\Enums\MaterialPricingStatus;
use App\Filament\Helpdesk\Resources\MaterialPricelists\MaterialPricelistResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListMaterialPricelists extends ListRecords
{
    /**
     * Keep filter state inside Livewire without synchronizing it to the URL; the filters are
     * rendered inline in the column headers.
     *
     * @var array<string, mixed>|null
     */
    public ?array $tableFilters = null;

    protected static string $resource = MaterialPricelistResource::class;

    protected ?string $subheading = 'Workflow harga resmi. Berbeda dari Product Price Index yang hanya menampilkan informasi harga historis.';

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'needs_action' => Tab::make('Needs Action')
                ->badge(fn (): int => MaterialPricelistResource::getEloquentQuery()->whereIn('status', MaterialPricelistResource::OPEN_STATUSES)->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', MaterialPricelistResource::OPEN_STATUSES)),
            'priced' => Tab::make('Priced')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', MaterialPricingStatus::Priced->value)),
        ];
    }
}
