<?php

namespace App\Filament\Casual\Resources\Attendances;

use App\Enums\AttendanceStatus;
use App\Enums\ShiftType;
use App\Filament\Casual\Resources\Attendances\Pages\CreateAttendance;
use App\Filament\Casual\Resources\Attendances\Pages\EditAttendance;
use App\Filament\Casual\Resources\Attendances\Pages\ListAttendances;
use App\Models\Attendance;
use App\Models\AttendancePeriod;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttendanceResource extends Resource
{
    protected static ?string $model = Attendance::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()->hasAnyRole(['super_admin', 'hr_staff', 'casual_staff']);
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasAnyRole(['super_admin', 'hr_staff']);
    }

    public static function form(Schema $schema): Schema
    {
        $isCasualStaff = auth()->user()->hasRole('casual_staff') && ! auth()->user()->hasAnyRole(['super_admin', 'hr_staff']);

        return $schema
            ->components([
                Section::make('Data Absensi')
                    ->schema([
                        Select::make('user_id')
                            ->label('Karyawan')
                            ->options(User::role('casual_staff')->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->hidden($isCasualStaff),

                        Select::make('period_id')
                            ->label('Periode')
                            ->options(
                                AttendancePeriod::where('is_locked', false)->pluck('name', 'id')
                            )
                            ->required(),

                        DatePicker::make('attendance_date')
                            ->label('Tanggal')
                            ->required(),

                        Grid::make(2)
                            ->schema([
                                TimePicker::make('check_in')
                                    ->label('Jam Masuk')
                                    ->nullable(),

                                TimePicker::make('check_out')
                                    ->label('Jam Keluar')
                                    ->nullable(),
                            ]),

                        Select::make('status')
                            ->label('Status')
                            ->options(AttendanceStatus::class)
                            ->required(),

                        Select::make('shift')
                            ->label('Shift')
                            ->options(ShiftType::class)
                            ->required(),

                        TextInput::make('overtime_hours')
                            ->label('Jam Lembur')
                            ->numeric()
                            ->step(0.5)
                            ->nullable(),

                        Textarea::make('notes')
                            ->label('Catatan')
                            ->nullable(),
                    ]),

                Hidden::make('recorded_by'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Karyawan')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('attendance_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('check_in')
                    ->label('Jam Masuk')
                    ->time('H:i'),

                TextColumn::make('check_out')
                    ->label('Jam Keluar')
                    ->time('H:i'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),

                TextColumn::make('shift')
                    ->label('Shift')
                    ->badge(),

                TextColumn::make('overtime_hours')
                    ->label('Lembur (jam)')
                    ->default('-'),

                TextColumn::make('recordedBy.name')
                    ->label('Dicatat Oleh')
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('period_id')
                    ->label('Periode')
                    ->options(AttendancePeriod::pluck('name', 'id'))
                    ->searchable(),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(AttendanceStatus::class),

                SelectFilter::make('shift')
                    ->label('Shift')
                    ->options(ShiftType::class),

                SelectFilter::make('user_id')
                    ->label('Karyawan')
                    ->options(User::role('casual_staff')->pluck('name', 'id'))
                    ->searchable()
                    ->hidden(fn () => ! auth()->user()->hasAnyRole(['super_admin', 'hr_staff'])),
            ])
            ->recordActions([
                EditAction::make()
                    ->hidden(fn () => ! auth()->user()->hasAnyRole(['super_admin', 'hr_staff'])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ])
                    ->hidden(fn () => ! auth()->user()->hasAnyRole(['super_admin', 'hr_staff'])),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (auth()->user()->hasAnyRole(['super_admin', 'hr_staff'])) {
            return $query;
        }

        return $query->where('user_id', auth()->id());
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttendances::route('/'),
            'create' => CreateAttendance::route('/create'),
            'edit' => EditAttendance::route('/{record}/edit'),
        ];
    }
}
