<?php

namespace App\Filament\Helpdesk\Resources\PaymentMethodGroups\Tables;

use App\Filament\Helpdesk\Resources\PaymentMethodGroups\PaymentMethodGroupResource;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentMethodGroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->width('48px'),

                TextColumn::make('name')
                    ->label('Group Name')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('items_count')
                    ->label('Mapped Methods')
                    ->counts('items')
                    ->sortable()
                    ->badge()
                    ->color('violet'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make()->iconButton()->tooltip('Edit'),
            ])
            ->toolbarActions([
                Action::make('create')
                    ->label('Add Group')
                    ->icon('heroicon-o-plus')
                    ->url(fn () => PaymentMethodGroupResource::getUrl('create')),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
