<?php

namespace App\Filament\Helpdesk\Resources\CasualShifts\RelationManagers;

use Filament\Actions\AssociateAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Staff Assigned';

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
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
            ])
            ->toolbarActions([
                AssociateAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('name', 'casual_staff')))
                    ->tooltip('Assign Staff'),
            ])
            ->recordActions([
                DissociateAction::make()->iconButton()->tooltip('Lepas dari shift ini'),
            ])
            ->groupedBulkActions([
                DissociateBulkAction::make(),
            ]);
    }
}
