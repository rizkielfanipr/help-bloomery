<?php

namespace App\Filament\Helpdesk\Resources\Trips;

use App\Enums\TripStatus;
use App\Filament\Exports\TripExporter;
use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\Trips\Pages\ListTrips;
use App\Filament\Helpdesk\Resources\Trips\Pages\ViewTrip;
use App\Models\Trip;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section as InfolistSection;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TripResource extends Resource
{
    use HasPermissions;

    protected static string $permissionPrefix = 'trips';

    protected static ?string $model = Trip::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Driver';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Perjalanan';

    protected static ?string $pluralModelLabel = 'Monitoring Perjalanan';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                InfolistSection::make('Detail Perjalanan')
                    ->schema([
                        TextEntry::make('code')
                            ->label('Kode Perjalanan')
                            ->fontFamily('mono')
                            ->weight('bold')
                            ->copyable()
                            ->copyMessage('Kode disalin')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        Grid::make(3)
                            ->schema([
                                TextEntry::make('driver.name')
                                    ->label('Driver'),

                                TextEntry::make('vehicle.license_plate')
                                    ->label('Kendaraan'),

                                TextEntry::make('tripRoute.name')
                                    ->label('Rute'),

                                TextEntry::make('trip_date')
                                    ->label('Tanggal')
                                    ->date('d M Y'),

                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge(),
                            ]),

                        TextEntry::make('started_at')
                            ->label('Mulai')
                            ->dateTime('d M Y H:i'),

                        TextEntry::make('completed_at')
                            ->label('Selesai')
                            ->dateTime('d M Y H:i'),
                    ]),

                InfolistSection::make('Odometer')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('odo_start')
                                    ->label('Odometer Awal')
                                    ->suffix(' km')
                                    ->fontFamily('mono')
                                    ->placeholder('—'),

                                TextEntry::make('odo_end')
                                    ->label('Odometer Akhir')
                                    ->suffix(' km')
                                    ->fontFamily('mono')
                                    ->placeholder('—'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                ImageEntry::make('odo_start_photo')
                                    ->label('Foto Odometer Awal')
                                    ->visibility('public')
                                    ->size(120)
                                    ->default(null),

                                ImageEntry::make('odo_end_photo')
                                    ->label('Foto Odometer Akhir')
                                    ->visibility('public')
                                    ->size(120)
                                    ->default(null),
                            ]),
                    ]),

                InfolistSection::make('Titik Perjalanan')
                    ->schema([
                        RepeatableEntry::make('waypointCheckins')
                            ->label('')
                            ->schema([
                                TextEntry::make('waypoint.name')
                                    ->label('Titik'),

                                TextEntry::make('checked_in_at')
                                    ->label('Waktu Check-in')
                                    ->dateTime('d M Y H:i')
                                    ->placeholder('Belum check-in'),

                                ImageEntry::make('attachment_path')
                                    ->label('Bukti')
                                    ->defaultImageUrl(fn ($record) => $record?->attachment_path ? null : null)
                                    ->visibility('private')
                                    ->size(80)
                                    ->default(null),
                            ])
                            ->columns(3),
                    ]),

                InfolistSection::make('Informasi Tambahan')
                    ->schema([
                        TextEntry::make('meal_allowance_amount')
                            ->label('Uang Makan')
                            ->money('IDR')
                            ->placeholder('—'),

                        TextEntry::make('notes')
                            ->label('Catatan')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                InfolistSection::make('Pengisian BBM')
                    ->hidden(fn (Trip $record): bool => ! $record->has_fuel_fillup || ! $record->fuelFillup)
                    ->schema([
                        TextEntry::make('fuelFillup.spbu_address')
                            ->label('Alamat SPBU')
                            ->columnSpanFull(),

                        TextEntry::make('fuelFillup.fuel_type')
                            ->label('Jenis BBM'),

                        TextEntry::make('fuelFillup.liters')
                            ->label('Liter')
                            ->suffix(' L'),

                        TextEntry::make('fuelFillup.price_per_liter')
                            ->label('Harga/Liter')
                            ->money('IDR'),

                        TextEntry::make('fuelFillup.total_price')
                            ->label('Total Harga')
                            ->money('IDR'),

                        Grid::make(2)
                            ->schema([
                                ImageEntry::make('fuelFillup.attachment_path')
                                    ->label('Foto Nota')
                                    ->visibility('public')
                                    ->size(160)
                                    ->default(null),

                                ImageEntry::make('fuelFillup.fuel_indicator_photo')
                                    ->label('Foto Indikator BBM')
                                    ->visibility('public')
                                    ->size(160)
                                    ->default(null),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->fontFamily('mono')
                    ->copyable()
                    ->copyMessage('Kode disalin')
                    ->placeholder('—'),

                TextColumn::make('driver.name')
                    ->label('Driver')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('tripRoute.name')
                    ->label('Rute')
                    ->searchable(),

                TextColumn::make('vehicle.license_plate')
                    ->label('Kendaraan'),

                TextColumn::make('trip_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('completed_at')
                    ->label('Selesai')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('has_fuel_fillup')
                    ->label('BBM')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Ada' : 'Tidak')
                    ->color(fn (bool $state): string => $state ? 'warning' : 'gray'),

                TextColumn::make('meal_allowance_amount')
                    ->label('Uang Makan')
                    ->money('IDR')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('driver')
                    ->label('Driver')
                    ->relationship('driver', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(TripStatus::class),

                SelectFilter::make('has_fuel_fillup')
                    ->label('BBM')
                    ->options([
                        '1' => 'Ada Pengisian BBM',
                        '0' => 'Tidak Ada BBM',
                    ]),
            ])
            ->defaultSort('trip_date', 'desc')
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

                ExportAction::make()->icon('heroicon-o-arrow-down-tray')->color('success')->iconButton()->tooltip('Export Excel')->exporter(TripExporter::class),

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
            'index' => ListTrips::route('/'),
            'view' => ViewTrip::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['driver', 'vehicle', 'tripRoute', 'waypointCheckins.waypoint', 'fuelFillup']);
    }
}
