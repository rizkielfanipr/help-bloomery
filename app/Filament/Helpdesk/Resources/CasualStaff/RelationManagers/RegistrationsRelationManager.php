<?php

namespace App\Filament\Helpdesk\Resources\CasualStaff\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RegistrationsRelationManager extends RelationManager
{
    protected static string $relationship = 'casualPositionRegistrations';

    protected static ?string $title = 'Riwayat Pendaftaran Casual';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('opening.work_date')
                    ->label('Tanggal Kerja')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('opening.casualPosition.name')
                    ->label('Posisi')
                    ->badge()
                    ->placeholder('—'),

                TextColumn::make('opening.branch.name')
                    ->label('Cabang / Lokasi')
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Tanggal Daftar')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ]);
    }
}
