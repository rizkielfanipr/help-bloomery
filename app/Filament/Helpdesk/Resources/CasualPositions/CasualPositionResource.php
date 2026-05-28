<?php

namespace App\Filament\Helpdesk\Resources\CasualPositions;

use App\Filament\Helpdesk\Resources\CasualPositions\Pages\CreateCasualPosition;
use App\Filament\Helpdesk\Resources\CasualPositions\Pages\EditCasualPosition;
use App\Filament\Helpdesk\Resources\CasualPositions\Pages\ListCasualPositions;
use App\Filament\Helpdesk\Resources\CasualPositions\Pages\ViewCasualPosition;
use App\Models\CasualPosition;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        '1' => 'Aktif',
                        '0' => 'Tidak Aktif',
                    ])
                    ->placeholder('Semua Status')
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['value'] === null || $data['value'] === '') {
                            return $query;
                        }

                        return $query->where('is_active', (bool) $data['value']);
                    }),
            ])
            ->recordActions([
                ViewAction::make()->iconButton()->tooltip('Lihat')->color('primary'),
                EditAction::make()->iconButton()->tooltip('Edit')->color('info'),
                DeleteAction::make()->iconButton()->tooltip('Hapus')->color('danger'),
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
            'view' => ViewCasualPosition::route('/{record}'),
            'edit' => EditCasualPosition::route('/{record}/edit'),
        ];
    }
}
