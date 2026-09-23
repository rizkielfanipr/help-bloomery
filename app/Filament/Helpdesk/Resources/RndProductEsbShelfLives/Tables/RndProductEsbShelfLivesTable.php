<?php

namespace App\Filament\Helpdesk\Resources\RndProductEsbShelfLives\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RndProductEsbShelfLivesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product_name')->label('Produk / Menu')->searchable()->sortable(),
                TextColumn::make('esb_menu_id')->label('Menu ID')->placeholder('—')->sortable(),
                TextColumn::make('product_code')->label('Kode')->placeholder('—')->searchable(),
                TextColumn::make('shelf_life_value')->label('Shelf Life')->formatStateUsing(fn ($record): string => rtrim(rtrim((string) $record->shelf_life_value, '0'), '.').' '.$record->shelf_life_unit),
                TextColumn::make('storage_condition')->label('Penyimpanan'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->defaultSort('product_name')
            ->recordActions([
                EditAction::make()->iconButton()->tooltip('Edit'),
                DeleteAction::make()->iconButton()->tooltip('Hapus')->requiresConfirmation(),
            ]);
    }
}
