<?php

namespace App\Filament\Helpdesk\Resources\Trips;

use App\Enums\TripStatus;
use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\Trips\Pages\ListTrips;
use App\Filament\Helpdesk\Resources\Trips\Pages\ViewTrip;
use App\Models\Trip;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
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

                                TextEntry::make('meal_allowance_amount')
                                    ->label('Uang Makan')
                                    ->money('IDR'),
                            ]),

                        TextEntry::make('started_at')
                            ->label('Mulai')
                            ->dateTime('d M Y H:i'),

                        TextEntry::make('completed_at')
                            ->label('Selesai')
                            ->dateTime('d M Y H:i'),
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

                                TextEntry::make('notes')
                                    ->label('Catatan')
                                    ->default('-'),
                            ])
                            ->columns(4),
                    ]),

                InfolistSection::make('Pengisian BBM')
                    ->hidden(fn (Trip $record): bool => ! $record->has_fuel_fillup || ! $record->fuelFillup)
                    ->schema([
                        TextEntry::make('fuelFillup.spbu_address')
                            ->label('Alamat SPBU'),

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

                        ImageEntry::make('fuelFillup.attachment_path')
                            ->label('Nota BBM')
                            ->visibility('private')
                            ->size(120)
                            ->default(null),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
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

                TextColumn::make('meal_allowance_amount')
                    ->label('Uang Makan')
                    ->money('IDR'),

                TextColumn::make('completed_at')
                    ->label('Selesai')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(TripStatus::class),
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
