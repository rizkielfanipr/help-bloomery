<?php

namespace App\Filament\Helpdesk\Resources\Locations\Pages;

use App\Filament\Helpdesk\Resources\Locations\LocationResource;
use App\Models\Branch;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;

class ListLocations extends ListRecords
{
    protected static string $resource = LocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view_floor_plan')
                ->label('Lihat Denah')
                ->icon('heroicon-o-squares-2x2')
                ->color('gray')
                ->form([
                    Select::make('branch_id')
                        ->label('Cabang')
                        ->options(fn (): array => $this->accessibleBranchOptions())
                        ->required()
                        ->searchable(),
                ])
                ->action(fn (array $data) => redirect(LocationFloorPlanPage::getUrl(['branch_id' => $data['branch_id']]))),
        ];
    }

    /** @return array<int, string> */
    private function accessibleBranchOptions(): array
    {
        $user = auth()->user();

        $query = Branch::query()->orderBy('name');

        if ($user && ! $user->canAccessAllBranches()) {
            $query->whereIn('id', $user->accessibleBranchIds());
        }

        return $query->pluck('name', 'id')->all();
    }
}
