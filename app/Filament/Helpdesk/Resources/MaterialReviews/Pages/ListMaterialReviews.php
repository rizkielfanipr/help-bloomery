<?php

namespace App\Filament\Helpdesk\Resources\MaterialReviews\Pages;

use App\Filament\Helpdesk\Resources\MaterialReviews\MaterialReviewResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListMaterialReviews extends ListRecords
{
    /**
     * Keep filter state inside Livewire without synchronizing it to the URL; the filters are
     * rendered inline in the column headers.
     *
     * @var array<string, mixed>|null
     */
    public ?array $tableFilters = null;

    protected static string $resource = MaterialReviewResource::class;

    protected ?string $subheading = 'Gerbang wajib sebelum Master Bahan/WIP ditautkan atau dikirim ke ESB.';

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'needs_action' => Tab::make('Needs Action')
                ->badge(fn (): int => MaterialReviewResource::scopeNeedsItAction(MaterialReviewResource::getEloquentQuery())->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => MaterialReviewResource::scopeNeedsItAction($query)),
        ];
    }
}
