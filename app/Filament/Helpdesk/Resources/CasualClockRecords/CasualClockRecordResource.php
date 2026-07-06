<?php

namespace App\Filament\Helpdesk\Resources\CasualClockRecords;

use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\CasualClockRecords\Pages\CreateCasualClockRecord;
use App\Filament\Helpdesk\Resources\CasualClockRecords\Pages\EditCasualClockRecord;
use App\Filament\Helpdesk\Resources\CasualClockRecords\Pages\ListCasualClockRecords;
use App\Filament\Helpdesk\Resources\CasualClockRecords\Pages\ViewCasualClockRecord;
use App\Models\Branch;
use App\Models\CasualClockRecord;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CasualClockRecordResource extends Resource
{
    use HasPermissions;

    protected static string $permissionPrefix = 'clock records';

    protected static ?string $model = CasualClockRecord::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-finger-print';

    protected static string|\UnitEnum|null $navigationGroup = 'Casual Staff';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Absensi';

    protected static ?string $pluralModelLabel = 'Monitoring Absensi Casual';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Info Staff')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('user_id')
                            ->label('Staff')
                            ->options(User::role('casual_staff')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('branch_id')
                            ->label('Cabang')
                            ->options(Branch::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        DatePicker::make('date')
                            ->label('Tanggal')
                            ->required()
                            ->maxDate(today()),
                    ]),
                ]),

            Section::make('Clock In')
                ->schema([
                    Grid::make(3)->schema([
                        DateTimePicker::make('clock_in_at')
                            ->label('Waktu Clock In')
                            ->seconds(false)
                            ->nullable(),

                        TextInput::make('clock_in_lat')
                            ->label('Latitude')
                            ->numeric()
                            ->nullable(),

                        TextInput::make('clock_in_lng')
                            ->label('Longitude')
                            ->numeric()
                            ->nullable(),
                    ]),

                    FileUpload::make('clock_in_photo')
                        ->label('Foto Clock In')
                        ->image()
                        ->disk('public')
                        ->directory('casual-clocks')
                        ->nullable()
                        ->maxSize(5120),
                ]),

            Section::make('Clock Out')
                ->schema([
                    Grid::make(3)->schema([
                        DateTimePicker::make('clock_out_at')
                            ->label('Waktu Clock Out')
                            ->seconds(false)
                            ->nullable(),

                        TextInput::make('clock_out_lat')
                            ->label('Latitude')
                            ->numeric()
                            ->nullable(),

                        TextInput::make('clock_out_lng')
                            ->label('Longitude')
                            ->numeric()
                            ->nullable(),
                    ]),

                    FileUpload::make('clock_out_photo')
                        ->label('Foto Clock Out')
                        ->image()
                        ->disk('public')
                        ->directory('casual-clocks')
                        ->nullable()
                        ->maxSize(5120),
                ]),

            Section::make('Keterangan')
                ->schema([
                    Textarea::make('notes')
                        ->label('Catatan')
                        ->nullable()
                        ->rows(3),
                ])
                ->collapsed(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->schema([
                    Section::make('Info Staff')
                        ->icon('heroicon-o-user-circle')
                        ->schema([
                            Grid::make(4)->schema([
                                TextEntry::make('user.name')
                                    ->label('Nama Staff'),
                                TextEntry::make('posisi')
                                    ->label('Posisi')
                                    ->state(fn (CasualClockRecord $record): ?string => $record->effectivePosition()?->name)
                                    ->placeholder('-'),
                                TextEntry::make('branch.name')
                                    ->label('Cabang')
                                    ->placeholder('-'),
                                TextEntry::make('date')
                                    ->label('Tanggal')
                                    ->date('d M Y'),
                            ]),
                        ]),

                    Grid::make(2)->schema([
                        Section::make('Clock In')
                            ->icon('heroicon-o-arrow-right-circle')
                            ->iconColor('success')
                            ->schema([
                                TextEntry::make('clock_in_at')
                                    ->label('Waktu')
                                    ->dateTime('d M Y  H:i')
                                    ->placeholder('Belum clock in'),
                                Grid::make(2)->schema([
                                    TextEntry::make('clock_in_lat')
                                        ->label('Latitude')
                                        ->placeholder('-')
                                        ->copyable(),
                                    TextEntry::make('clock_in_lng')
                                        ->label('Longitude')
                                        ->placeholder('-')
                                        ->copyable(),
                                ]),
                                ImageEntry::make('clock_in_photo')
                                    ->label('Foto')
                                    ->disk('public')
                                    ->size(240)
                                    ->default(null),
                            ]),

                        Section::make('Clock Out')
                            ->icon('heroicon-o-arrow-left-circle')
                            ->iconColor('danger')
                            ->schema([
                                TextEntry::make('clock_out_at')
                                    ->label('Waktu')
                                    ->dateTime('d M Y  H:i')
                                    ->placeholder('Belum clock out'),
                                Grid::make(2)->schema([
                                    TextEntry::make('clock_out_lat')
                                        ->label('Latitude')
                                        ->placeholder('-')
                                        ->copyable(),
                                    TextEntry::make('clock_out_lng')
                                        ->label('Longitude')
                                        ->placeholder('-')
                                        ->copyable(),
                                ]),
                                ImageEntry::make('clock_out_photo')
                                    ->label('Foto')
                                    ->disk('public')
                                    ->size(240)
                                    ->default(null),
                            ]),
                    ]),

                    Section::make('Ringkasan')
                        ->icon('heroicon-o-banknotes')
                        ->schema([
                            Grid::make(4)->schema([
                                TextEntry::make('durasi_kerja')
                                    ->label('Durasi Kerja')
                                    ->state(fn (CasualClockRecord $record): ?string => $record->formattedWorkDuration())
                                    ->placeholder('-'),

                                TextEntry::make('fee_harian')
                                    ->label('Fee Harian')
                                    ->state(fn (CasualClockRecord $record): ?float => $record->effectivePosition()?->fee_per_day)
                                    ->money('IDR')
                                    ->placeholder('-'),

                                TextEntry::make('durasi_lembur')
                                    ->label('Durasi Lembur')
                                    ->state(function (CasualClockRecord $record): ?string {
                                        $hours = $record->overtimeRequest?->approved_hours;
                                        if ($hours === null) {
                                            return null;
                                        }
                                        $totalMins = (int) round($hours * 60);
                                        $h = intdiv($totalMins, 60);
                                        $m = $totalMins % 60;

                                        return $h > 0 ? "{$h}j {$m}m" : "{$m}m";
                                    })
                                    ->placeholder('-'),

                                TextEntry::make('overtimeRequest.overtime_fee')
                                    ->label('Fee Lembur')
                                    ->money('IDR')
                                    ->placeholder('-'),
                            ]),
                        ]),

                    TextEntry::make('notes')
                        ->label('Catatan')
                        ->placeholder('Tidak ada catatan')
                        ->columnSpanFull()
                        ->hidden(fn (CasualClockRecord $record): bool => blank($record->notes)),
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

                TextColumn::make('posisi')
                    ->label('Posisi')
                    ->state(fn (CasualClockRecord $record): ?string => $record->effectivePosition()?->name)
                    ->placeholder('-'),

                TextColumn::make('branch.name')
                    ->label('Cabang')
                    ->placeholder('-'),

                TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('clock_in_at')
                    ->label('Clock In')
                    ->time('H:i')
                    ->placeholder('-'),

                TextColumn::make('clock_out_at')
                    ->label('Clock Out')
                    ->time('H:i')
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('branch_id')
                    ->label('Cabang')
                    ->options(Branch::where('is_active', true)->pluck('name', 'id'))
                    ->searchable(),

                SelectFilter::make('user_id')
                    ->label('Staff')
                    ->options(User::role('casual_staff')->pluck('name', 'id'))
                    ->searchable(),
            ])
            ->defaultSort('date', 'desc')
            ->recordActions([
                ViewAction::make()->iconButton()->tooltip('Lihat')->color('info'),
                EditAction::make()->iconButton()->tooltip('Edit')->color('warning'),
                DeleteAction::make()->iconButton()->tooltip('Hapus')->color('danger'),
            ])
            ->toolbarActions([
                Action::make('create')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->iconButton()
                    ->tooltip('Tambah Absensi')
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

                Action::make('export_excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->iconButton()
                    ->tooltip('Export Excel')
                    ->modalHeading('Export Data Absensi')
                    ->modalDescription('Pilih rentang tanggal untuk data yang akan diekspor. Kosongkan untuk mengekspor semua data.')
                    ->modalSubmitActionLabel('Export')
                    ->modalWidth('md')
                    ->form([
                        DatePicker::make('date_from')
                            ->label('Dari Tanggal')
                            ->maxDate(today())
                            ->default(today()->startOfMonth()),
                        DatePicker::make('date_until')
                            ->label('Sampai Tanggal')
                            ->maxDate(today())
                            ->default(today()),
                    ])
                    ->action(function (array $data, $livewire): void {
                        $filters = $livewire->tableFilters ?? [];

                        $url = route('helpdesk.exports.casual-clock-records', array_filter([
                            'branch_id' => $filters['branch_id']['value'] ?? null,
                            'user_id' => $filters['user_id']['value'] ?? null,
                            'date_from' => $data['date_from'] ?? null,
                            'date_until' => $data['date_until'] ?? null,
                        ]));

                        $livewire->js("window.open('$url', '_blank')");
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
            'create' => CreateCasualClockRecord::route('/create'),
            'view' => ViewCasualClockRecord::route('/{record}'),
            'edit' => EditCasualClockRecord::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user.casualPosition', 'branch', 'overtimeRequest']);
    }
}
