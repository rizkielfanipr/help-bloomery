<?php

namespace App\Filament\Helpdesk\Resources\QualityControlChecklistItems\Tables;

use App\Models\QualityControlChecklistItem;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class QualityControlChecklistItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('section_code')->label('Section')->badge()->sortable(),
                TextColumn::make('section_name')->label('Nama Section')->searchable()->toggleable(),
                TextColumn::make('question')->label('Poin Audit')->searchable()->wrap(),
                TextColumn::make('points')->label('Poin')->numeric()->sortable(),
                IconColumn::make('is_critical')->label('Critical')->boolean(),
                IconColumn::make('requires_photo')->label('Foto')->boolean(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('section_code')
                    ->label('Section')
                    ->options(fn (): array => QualityControlChecklistItem::query()
                        ->orderBy('section_code')
                        ->pluck('section_name', 'section_code')
                        ->all()),
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([1 => 'Aktif', 0 => 'Nonaktif']),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }
}
