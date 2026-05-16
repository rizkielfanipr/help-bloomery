<?php

namespace App\Filament\Casual\Resources\AttendancePeriods;

use App\Filament\Casual\Resources\AttendancePeriods\Pages\CreateAttendancePeriod;
use App\Filament\Casual\Resources\AttendancePeriods\Pages\EditAttendancePeriod;
use App\Filament\Casual\Resources\AttendancePeriods\Pages\ListAttendancePeriods;
use App\Models\AttendancePeriod;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttendancePeriodResource extends Resource
{
    protected static ?string $model = AttendancePeriod::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()->hasAnyRole(['super_admin', 'hr_staff']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Periode Absensi')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Periode')
                            ->required(),

                        DatePicker::make('start_date')
                            ->label('Tanggal Mulai')
                            ->required(),

                        DatePicker::make('end_date')
                            ->label('Tanggal Selesai')
                            ->required(),

                        Toggle::make('is_locked')
                            ->label('Dikunci')
                            ->default(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Periode')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('start_date')
                    ->label('Tanggal Mulai')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('Tanggal Selesai')
                    ->date('d M Y')
                    ->sortable(),

                IconColumn::make('is_locked')
                    ->label('Dikunci')
                    ->boolean(),

                TextColumn::make('attendances_count')
                    ->label('Total Absensi')
                    ->counts('attendances')
                    ->sortable(),
            ])
            ->filters([])
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
            'index' => ListAttendancePeriods::route('/'),
            'create' => CreateAttendancePeriod::route('/create'),
            'edit' => EditAttendancePeriod::route('/{record}/edit'),
        ];
    }
}
