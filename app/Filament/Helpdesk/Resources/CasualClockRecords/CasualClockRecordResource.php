<?php

namespace App\Filament\Helpdesk\Resources\CasualClockRecords;

use App\Filament\Helpdesk\Resources\CasualClockRecords\Pages\ListCasualClockRecords;
use App\Filament\Helpdesk\Resources\CasualClockRecords\Pages\ViewCasualClockRecord;
use App\Models\CasualClockRecord;
use App\Models\CasualShift;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CasualClockRecordResource extends Resource
{
    protected static ?string $model = CasualClockRecord::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-finger-print';

    protected static string|\UnitEnum|null $navigationGroup = 'Casual Staff';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Absensi';

    protected static ?string $pluralModelLabel = 'Monitoring Absensi Casual';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Info Staff')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('user.name')->label('Nama'),
                        TextEntry::make('user.casualPosition.name')->label('Posisi')->placeholder('-'),
                        TextEntry::make('shift.name')->label('Shift'),
                        TextEntry::make('date')->label('Tanggal')->date('d M Y'),
                    ]),
                ]),

            Section::make('Clock In')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('clock_in_at')->label('Waktu')->dateTime('d M Y H:i:s')->placeholder('-'),
                        IconEntry::make('is_late')->label('Terlambat')->boolean(),
                        TextEntry::make('late_minutes')->label('Keterlambatan')->suffix(' menit')->placeholder('-'),
                        TextEntry::make('clock_in_lat')->label('Latitude')->placeholder('-'),
                        TextEntry::make('clock_in_lng')->label('Longitude')->placeholder('-'),
                    ]),
                    ImageEntry::make('clock_in_photo')
                        ->label('Foto Clock In')
                        ->disk('public')
                        ->size(200)
                        ->default(null),
                ]),

            Section::make('Clock Out')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('clock_out_at')->label('Waktu')->dateTime('d M Y H:i:s')->placeholder('-'),
                        IconEntry::make('is_early_out')->label('Pulang Awal')->boolean(),
                        TextEntry::make('early_out_minutes')->label('Lebih Awal')->suffix(' menit')->placeholder('-'),
                        TextEntry::make('clock_out_lat')->label('Latitude')->placeholder('-'),
                        TextEntry::make('clock_out_lng')->label('Longitude')->placeholder('-'),
                    ]),
                    ImageEntry::make('clock_out_photo')
                        ->label('Foto Clock Out')
                        ->disk('public')
                        ->size(200)
                        ->default(null),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Staff')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.casualPosition.name')
                    ->label('Posisi')
                    ->placeholder('-'),

                TextColumn::make('shift.name')
                    ->label('Shift'),

                TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('clock_in_at')
                    ->label('Clock In')
                    ->time('H:i')
                    ->placeholder('-'),

                IconColumn::make('is_late')
                    ->label('Terlambat')
                    ->boolean(),

                TextColumn::make('clock_out_at')
                    ->label('Clock Out')
                    ->time('H:i')
                    ->placeholder('-'),

                IconColumn::make('is_early_out')
                    ->label('Pulang Awal')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('shift_id')
                    ->label('Shift')
                    ->options(CasualShift::pluck('name', 'id'))
                    ->searchable(),

                SelectFilter::make('user_id')
                    ->label('Staff')
                    ->options(User::role('casual_staff')->pluck('name', 'id'))
                    ->searchable(),
            ])
            ->defaultSort('date', 'desc')
            ->recordActions([
                ViewAction::make()->iconButton()->tooltip('Lihat')->color('info'),
            ])
            ->toolbarActions([
                Action::make('import_excel')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
                    ->iconButton()
                    ->tooltip('Import Excel')
                    ->action(function (): void {
                        Notification::make()
                            ->title('Segera hadir')
                            ->body('Fitur Import Excel belum tersedia.')
                            ->warning()
                            ->send();
                    }),

                Action::make('export_excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->iconButton()
                    ->tooltip('Export Excel')
                    ->action(function (): void {
                        Notification::make()
                            ->title('Segera hadir')
                            ->body('Fitur Export Excel belum tersedia.')
                            ->warning()
                            ->send();
                    }),

                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCasualClockRecords::route('/'),
            'view' => ViewCasualClockRecord::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user.casualPosition', 'shift']);
    }
}
