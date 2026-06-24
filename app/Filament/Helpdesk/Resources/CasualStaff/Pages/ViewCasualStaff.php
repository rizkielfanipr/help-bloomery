<?php

namespace App\Filament\Helpdesk\Resources\CasualStaff\Pages;

use App\Filament\Helpdesk\Resources\CasualStaff\CasualStaffResource;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class ViewCasualStaff extends ViewRecord
{
    protected static string $resource = CasualStaffResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->record->name;
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi Pribadi')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('name')
                            ->label('Nama Lengkap'),

                        TextEntry::make('phone')
                            ->label('Nomor HP')
                            ->placeholder('—'),
                    ]),

                    Grid::make(2)->schema([
                        TextEntry::make('casualPosition.name')
                            ->label('Posisi Default')
                            ->badge()
                            ->placeholder('—'),

                        TextEntry::make('is_active')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Aktif' : 'Tidak Aktif')
                            ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                    ]),
                ]),

            Section::make('Data Bank')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('bank_name')
                            ->label('Nama Bank')
                            ->placeholder('—'),

                        TextEntry::make('bank_account_number')
                            ->label('Nomor Rekening')
                            ->placeholder('—'),
                    ]),
                ]),
        ]);
    }
}
