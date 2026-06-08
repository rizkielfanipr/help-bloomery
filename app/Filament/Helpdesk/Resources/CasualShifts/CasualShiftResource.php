<?php

namespace App\Filament\Helpdesk\Resources\CasualShifts;

use App\Filament\Helpdesk\Resources\CasualShifts\Pages\CreateCasualShift;
use App\Filament\Helpdesk\Resources\CasualShifts\Pages\EditCasualShift;
use App\Filament\Helpdesk\Resources\CasualShifts\Pages\ListCasualShifts;
use App\Filament\Helpdesk\Resources\CasualShifts\RelationManagers\UsersRelationManager;
use App\Models\CasualShift;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CasualShiftResource extends Resource
{
    protected static ?string $model = CasualShift::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'Casual Staff';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Shift';

    protected static ?string $pluralModelLabel = 'Data Shift';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Jadwal Shift')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Shift')
                            ->required()
                            ->placeholder('Contoh: Shift Pagi'),

                        TimePicker::make('start_time')
                            ->label('Jam Masuk')
                            ->required()
                            ->seconds(false),

                        TimePicker::make('end_time')
                            ->label('Jam Keluar')
                            ->required()
                            ->seconds(false),

                        TextInput::make('tolerance_late_minutes')
                            ->label('Toleransi Keterlambatan (menit)')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->suffix('menit')
                            ->helperText('Batas waktu setelah jam masuk sebelum dianggap terlambat'),

                        TextInput::make('tolerance_early_out_minutes')
                            ->label('Toleransi Pulang Awal (menit)')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->suffix('menit')
                            ->helperText('Toleransi sebelum jam keluar yang masih diperbolehkan'),

                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Pengaturan Lokasi')
                    ->description('Atur lokasi wajib untuk clock in/out di shift ini')
                    ->schema([
                        Toggle::make('location_required')
                            ->label('Wajib di Lokasi')
                            ->helperText('Jika aktif, staff harus berada dalam radius lokasi yang ditentukan')
                            ->live()
                            ->default(false),

                        TextInput::make('location_lat')
                            ->label('Latitude')
                            ->numeric()
                            ->nullable()
                            ->placeholder('-7.7956')
                            ->visible(fn ($get) => $get('location_required')),

                        TextInput::make('location_lng')
                            ->label('Longitude')
                            ->numeric()
                            ->nullable()
                            ->placeholder('110.3695')
                            ->visible(fn ($get) => $get('location_required')),

                        TextInput::make('location_radius_meters')
                            ->label('Radius (meter)')
                            ->numeric()
                            ->minValue(10)
                            ->default(100)
                            ->suffix('m')
                            ->visible(fn ($get) => $get('location_required')),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Shift')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('start_time')
                    ->label('Jam Masuk')
                    ->time('H:i'),

                TextColumn::make('end_time')
                    ->label('Jam Keluar')
                    ->time('H:i'),

                TextColumn::make('tolerance_late_minutes')
                    ->label('Toleransi Terlambat')
                    ->suffix(' menit'),

                IconColumn::make('location_required')
                    ->label('Wajib Lokasi')
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
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
                    ->tooltip('Tambah Shift')
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
        return [
            UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCasualShifts::route('/'),
            'create' => CreateCasualShift::route('/create'),
            'edit' => EditCasualShift::route('/{record}/edit'),
        ];
    }
}
