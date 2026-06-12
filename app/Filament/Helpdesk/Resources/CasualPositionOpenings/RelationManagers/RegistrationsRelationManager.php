<?php

namespace App\Filament\Helpdesk\Resources\CasualPositionOpenings\RelationManagers;

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RegistrationsRelationManager extends RelationManager
{
    protected static string $relationship = 'registrations';

    protected static ?string $title = 'Pendaftar';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.phone')
                    ->label('No. Telepon')
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label('Waktu Daftar')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'asc')
            ->recordActions([
                DeleteAction::make()->tooltip('Batalkan Pendaftaran')->iconButton(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
