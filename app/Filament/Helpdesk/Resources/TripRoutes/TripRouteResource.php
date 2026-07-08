<?php

namespace App\Filament\Helpdesk\Resources\TripRoutes;

use App\Filament\Exports\TripRouteExporter;
use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\TripRoutes\Pages\CreateTripRoute;
use App\Filament\Helpdesk\Resources\TripRoutes\Pages\EditTripRoute;
use App\Filament\Helpdesk\Resources\TripRoutes\Pages\ListTripRoutes;
use App\Models\TripRoute;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TripRouteResource extends Resource
{
    use HasPermissions;

    protected static string $permissionPrefix = 'trip routes';

    protected static ?string $model = TripRoute::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map';

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Driver';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Rute Perjalanan';

    protected static ?string $pluralModelLabel = 'Rute Perjalanan';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Rute')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Rute')
                            ->required()
                            ->placeholder('Contoh: Jakarta - Yogyakarta'),

                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->nullable()
                            ->rows(2),

                        TextInput::make('meal_allowance_amount')
                            ->label('Uang Makan (Rp)')
                            ->numeric()
                            ->nullable()
                            ->minValue(0)
                            ->prefix('Rp')
                            ->helperText('Kosongkan jika tidak ada uang makan untuk rute ini'),

                        Toggle::make('requires_waypoint_attachment')
                            ->label('Wajib Upload Bukti per Titik')
                            ->helperText('Jika aktif, driver wajib upload foto/dokumen di setiap titik perjalanan')
                            ->default(false),

                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Titik-Titik Perjalanan')
                    ->description('Urutkan titik perjalanan sesuai urutan kunjungan')
                    ->schema([
                        Repeater::make('waypoints')
                            ->relationship()
                            ->label('')
                            ->addActionLabel('Tambah Titik')
                            ->schema([
                                TextInput::make('urutan')
                                    ->label('Urutan')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1),

                                TextInput::make('name')
                                    ->label('Nama Titik')
                                    ->required()
                                    ->placeholder('Contoh: Gudang Sleman'),

                                Textarea::make('description')
                                    ->label('Keterangan')
                                    ->nullable()
                                    ->rows(1),
                            ])
                            ->columns(3)
                            ->orderColumn('urutan')
                            ->collapsible(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Rute')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('meal_allowance_amount')
                    ->label('Uang Makan')
                    ->money('IDR')
                    ->placeholder('-'),

                TextColumn::make('waypoints_count')
                    ->label('Jml Titik')
                    ->counts('waypoints')
                    ->sortable(),

                IconColumn::make('requires_waypoint_attachment')
                    ->label('Wajib Bukti')
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make()->iconButton()->tooltip('Edit')->color('warning'),
                DeleteAction::make()->iconButton()->tooltip('Hapus')->color('danger'),
            ])
            ->toolbarActions([
                Action::make('create')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->iconButton()
                    ->tooltip('Tambah Rute')
                    ->visible(fn () => static::canCreate())
                    ->url(static::getUrl('create')),

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

                ExportAction::make()->icon('heroicon-o-arrow-down-tray')->color('success')->iconButton()->tooltip('Export Excel')->exporter(TripRouteExporter::class),

                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
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
            'index' => ListTripRoutes::route('/'),
            'create' => CreateTripRoute::route('/create'),
            'edit' => EditTripRoute::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
