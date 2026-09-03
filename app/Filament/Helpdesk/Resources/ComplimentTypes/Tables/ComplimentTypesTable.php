<?php

namespace App\Filament\Helpdesk\Resources\ComplimentTypes\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ComplimentTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')->label('#')->sortable(),
                TextColumn::make('name')->label('Jenis Compliment')->searchable()->sortable(),
                TextColumn::make('sales_report_compliments_count')->label('Digunakan')->counts('salesReportCompliments')->badge(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make()->iconButton()->tooltip('Edit'),
                DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Hapus')
                    ->visible(fn ($record): bool => ! $record->salesReportCompliments()->exists())
                    ->requiresConfirmation(),
            ]);
    }
}
