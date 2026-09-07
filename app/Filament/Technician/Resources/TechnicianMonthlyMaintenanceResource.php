<?php

namespace App\Filament\Technician\Resources;

use App\Filament\Technician\Resources\TechnicianMonthlyMaintenanceResource\Pages;
use App\Models\TechnicianMonthlyMaintenance;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class TechnicianMonthlyMaintenanceResource extends Resource
{
    protected static ?string $model = TechnicianMonthlyMaintenance::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|UnitEnum|null $navigationGroup = null;

    public static function getNavigationLabel(): string
    {
        return 'Pemeliharaan';
    }

    public static function canViewAny(): bool
    {
        return Auth::user()?->can('view technician monthly maintenance') ?? false;
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->can('create technician monthly maintenance') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return Auth::user()?->can('edit technician monthly maintenance') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return Auth::user()?->can('delete technician monthly maintenance') ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return Auth::user()?->can('delete technician monthly maintenance') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Informasi Pemeliharaan Bulanan Teknisi')
                    ->schema([
                        Select::make('branch_id')
                            ->label('Cabang')
                            ->relationship('branch', 'name')
                            ->required()
                            ->readOnly(), // Technician can only log for their own branch
                        TextInput::make('year')
                            ->label('Tahun')
                            ->required()
                            ->numeric()
                            ->minValue(2020)
                            ->maxValue(2100),
                        Select::make('month')
                            ->label('Bulan')
                            ->required()
                            ->options([
                                1 => 'Januari',
                                2 => 'Februari',
                                3 => 'Maret',
                                4 => 'April',
                                5 => 'Mei',
                                6 => 'Juni',
                                7 => 'Juli',
                                8 => 'Agustus',
                                9 => 'September',
                                10 => 'Oktober',
                                11 => 'November',
                                12 => 'Desember',
                            ]),
                        TextInput::make('points')
                            ->label('Poin')
                            ->required()
                            ->numeric()
                            ->minValue(0),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('branch.name')
                    ->label('Cabang')
                    ->searchable(),
                TextColumn::make('year')
                    ->label('Tahun')
                    ->sortable(),
                TextColumn::make('month')
                    ->label('Bulan')
                    ->formatStateUsing(fn (string $state): string => [
                        1 => 'Januari',
                        2 => 'Februari',
                        3 => 'Maret',
                        4 => 'April',
                        5 => 'Mei',
                        6 => 'Juni',
                        7 => 'Juli',
                        8 => 'Agustus',
                        9 => 'September',
                        10 => 'Oktober',
                        11 => 'November',
                        12 => 'Desember',
                    ][$state]),
                TextColumn::make('points')
                    ->label('Poin')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Diupdate')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTechnicianMonthlyMaintenances::route('/'),
            'create' => Pages\CreateTechnicianMonthlyMaintenance::route('/create'),
            'edit' => Pages\EditTechnicianMonthlyMaintenance::route('/edit/{record}'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('branch_id', Auth::user()->branch_id);
    }
}
