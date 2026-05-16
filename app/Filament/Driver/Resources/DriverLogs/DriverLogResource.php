<?php

namespace App\Filament\Driver\Resources\DriverLogs;

use App\Enums\DeliveryStatus;
use App\Filament\Driver\Resources\DriverLogs\Pages\CreateDriverLog;
use App\Filament\Driver\Resources\DriverLogs\Pages\EditDriverLog;
use App\Filament\Driver\Resources\DriverLogs\Pages\ListDriverLogs;
use App\Models\DriverLog;
use App\Models\User;
use App\Models\Vehicle;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DriverLogResource extends Resource
{
    protected static ?string $model = DriverLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()->hasAnyRole(['super_admin', 'driver']);
    }

    public static function form(Schema $schema): Schema
    {
        $isManager = auth()->user()->hasAnyRole(['super_admin']);

        return $schema
            ->components([
                Section::make('Informasi Perjalanan')
                    ->schema([
                        Select::make('vehicle_id')
                            ->label('Kendaraan')
                            ->options(
                                Vehicle::where('is_active', true)->pluck('license_plate', 'id')
                            )
                            ->required(),

                        DatePicker::make('log_date')
                            ->label('Tanggal')
                            ->required()
                            ->default(today()),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('odometer_start')
                                    ->label('Odometer Awal (km)')
                                    ->numeric(),

                                TextInput::make('odometer_end')
                                    ->label('Odometer Akhir (km)')
                                    ->numeric(),
                            ]),
                    ]),

                Section::make('Titik Pengantaran')
                    ->schema([
                        Repeater::make('deliveryPoints')
                            ->relationship()
                            ->label('Titik Pengantaran')
                            ->addActionLabel('Tambah Titik Pengantaran')
                            ->schema([
                                TextInput::make('urutan')
                                    ->label('Urutan')
                                    ->numeric()
                                    ->required(),

                                TextInput::make('nama_toko')
                                    ->label('Nama Toko/Customer')
                                    ->required(),

                                Textarea::make('alamat')
                                    ->label('Alamat')
                                    ->required()
                                    ->rows(2),

                                Grid::make(2)
                                    ->schema([
                                        TimePicker::make('waktu_tiba')
                                            ->label('Waktu Tiba'),

                                        TimePicker::make('waktu_selesai')
                                            ->label('Waktu Selesai'),
                                    ]),

                                TextInput::make('jumlah_produk')
                                    ->label('Jumlah Produk (pcs)')
                                    ->numeric(),

                                Select::make('status')
                                    ->label('Status')
                                    ->options(DeliveryStatus::class)
                                    ->default(DeliveryStatus::Pending->value),

                                Textarea::make('notes')
                                    ->label('Catatan')
                                    ->rows(2)
                                    ->nullable(),
                            ])
                            ->columns(2),
                    ]),

                Section::make('Catatan')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Catatan Umum')
                            ->nullable(),
                    ]),

                Hidden::make('driver_id'),

                Section::make('Persetujuan')
                    ->hidden(fn () => ! $isManager)
                    ->schema([
                        Select::make('approved_by')
                            ->label('Disetujui Oleh')
                            ->options(
                                User::role('super_admin')->pluck('name', 'id')
                            )
                            ->searchable()
                            ->nullable(),
                    ]),
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

                TextColumn::make('vehicle.license_plate')
                    ->label('Kendaraan')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('log_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('delivery_points_count')
                    ->label('Titik Pengantaran')
                    ->counts('deliveryPoints')
                    ->sortable(),

                TextColumn::make('total_km')
                    ->label('Total KM')
                    ->getStateUsing(fn (DriverLog $record): ?string => ($record->odometer_start && $record->odometer_end)
                        ? ($record->odometer_end - $record->odometer_start).' km'
                        : '-'),

                TextColumn::make('approvedBy.name')
                    ->label('Disetujui')
                    ->default('-'),

                TextColumn::make('approved_at')
                    ->label('Status')
                    ->getStateUsing(fn (DriverLog $record): string => $record->approved_at ? 'Disetujui' : 'Menunggu')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Disetujui' ? 'success' : 'warning'),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);

        if (auth()->user()->hasRole('super_admin')) {
            return $query;
        }

        return $query->where('driver_id', auth()->id());
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDriverLogs::route('/'),
            'create' => CreateDriverLog::route('/create'),
            'edit' => EditDriverLog::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
