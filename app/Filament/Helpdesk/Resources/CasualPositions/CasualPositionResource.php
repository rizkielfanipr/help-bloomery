<?php

namespace App\Filament\Helpdesk\Resources\CasualPositions;

use App\Filament\Helpdesk\Resources\CasualPositions\Pages\CreateCasualPosition;
use App\Filament\Helpdesk\Resources\CasualPositions\Pages\EditCasualPosition;
use App\Filament\Helpdesk\Resources\CasualPositions\Pages\ListCasualPositions;
use App\Models\CasualPosition;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CasualPositionResource extends Resource
{
    protected static ?string $model = CasualPosition::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static string|\UnitEnum|null $navigationGroup = 'Casual Staff';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Posisi';

    protected static ?string $pluralModelLabel = 'Posisi Casual';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Posisi')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Posisi')
                            ->required()
                            ->placeholder('Contoh: Kasir, Pramuniaga, Loader'),

                        TextInput::make('fee_per_day')
                            ->label('Fee per Hari (Rp)')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->prefix('Rp'),

                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->nullable()
                            ->rows(2)
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Posisi')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('fee_per_day')
                    ->label('Fee/Hari')
                    ->money('IDR')
                    ->sortable(),

                TextColumn::make('users_count')
                    ->label('Staff Aktif')
                    ->counts('users')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCasualPositions::route('/'),
            'create' => CreateCasualPosition::route('/create'),
            'edit' => EditCasualPosition::route('/{record}/edit'),
        ];
    }
}
