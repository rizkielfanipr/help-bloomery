<?php

namespace App\Filament\Helpdesk\Resources\CasualPositionOpenings;

use App\Filament\Helpdesk\Resources\CasualPositionOpenings\Pages\CreateCasualPositionOpening;
use App\Filament\Helpdesk\Resources\CasualPositionOpenings\Pages\EditCasualPositionOpening;
use App\Filament\Helpdesk\Resources\CasualPositionOpenings\Pages\ListCasualPositionOpenings;
use App\Filament\Helpdesk\Resources\CasualPositionOpenings\Pages\ViewCasualPositionOpening;
use App\Filament\Helpdesk\Resources\CasualPositionOpenings\RelationManagers\RegistrationsRelationManager;
use App\Models\Branch;
use App\Models\CasualPosition;
use App\Models\CasualPositionOpening;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconSize;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class CasualPositionOpeningResource extends Resource
{
    protected static ?string $model = CasualPositionOpening::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static string|\UnitEnum|null $navigationGroup = 'Human Resources';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Lowongan';

    protected static ?string $pluralModelLabel = 'Lowongan Posisi';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'helpdesk_manager', 'hr_staff']) ?? false;
    }

    private static function colLabel(string $icon, string $label): HtmlString
    {
        $svg = \Filament\Support\generate_icon_html($icon, size: IconSize::Small)?->toHtml() ?? '';

        return new HtmlString(
            '<span class="inline-flex items-center gap-1.5">'.$svg.e($label).'</span>'
        );
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detail Lowongan')
                    ->schema([
                        Select::make('casual_position_id')
                            ->label('Posisi')
                            ->options(CasualPosition::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->required(),

                        Select::make('branch_id')
                            ->label('Cabang / Lokasi')
                            ->options(Branch::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->nullable(),

                        // Create: pick multiple dates, each with its own slots
                        Repeater::make('schedules')
                            ->label('Jadwal (Tanggal & Slot)')
                            ->schema([
                                DatePicker::make('work_date')
                                    ->label('Tanggal')
                                    ->required()
                                    ->minDate(today()),
                                TextInput::make('total_slots')
                                    ->label('Jumlah Slot')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->default(1),
                            ])
                            ->columns(2)
                            ->minItems(1)
                            ->defaultItems(1)
                            ->addActionLabel('Tambah Jadwal')
                            ->columnSpanFull()
                            ->hidden(fn (string $operation): bool => $operation !== 'create'),

                        // Edit: single date + slot
                        DatePicker::make('work_date')
                            ->label('Tanggal Kerja')
                            ->required()
                            ->minDate(today())
                            ->hidden(fn (string $operation): bool => $operation !== 'edit'),

                        TextInput::make('total_slots')
                            ->label('Jumlah Slot')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->hidden(fn (string $operation): bool => $operation !== 'edit'),

                        Toggle::make('is_active')
                            ->label('Buka Pendaftaran')
                            ->default(true),

                        Textarea::make('description')
                            ->label('Catatan / Deskripsi')
                            ->nullable()
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('work_date', 'asc')
            ->defaultPaginationPageOption(20)
            ->paginationPageOptions([20, 50, 100])
            ->searchPlaceholder('Cari posisi / cabang...')
            ->columns([
                TextColumn::make('work_date')
                    ->label(static::colLabel('heroicon-m-calendar-days', 'Tanggal Kerja'))
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('casualPosition.name')
                    ->label(static::colLabel('heroicon-m-briefcase', 'Posisi'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('branch.name')
                    ->label(static::colLabel('heroicon-m-building-office-2', 'Cabang'))
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('registrations_count')
                    ->label(static::colLabel('heroicon-m-users', 'Slot'))
                    ->counts('registrations')
                    ->formatStateUsing(fn (int $state, CasualPositionOpening $record): string => $state.'/'.$record->total_slots)
                    ->color(fn (int $state, CasualPositionOpening $record): string => $state >= $record->total_slots ? 'danger' : 'success'),

                TextColumn::make('is_active')
                    ->label(static::colLabel('heroicon-m-shield-check', 'Status'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Dibuka' : 'Ditutup')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options(['1' => 'Dibuka', '0' => 'Ditutup'])
                    ->placeholder('Semua Status')
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['value'] === null || $data['value'] === '') {
                            return $query;
                        }

                        return $query->where('is_active', (bool) $data['value']);
                    }),

                SelectFilter::make('casual_position_id')
                    ->label('Posisi')
                    ->options(CasualPosition::where('is_active', true)->pluck('name', 'id'))
                    ->placeholder('Semua Posisi'),
            ])
            ->filtersTriggerAction(
                fn (Action $action) => $action
                    ->button()
                    ->label('Filter')
                    ->icon('heroicon-m-funnel')
                    ->color('gray'),
            )
            ->recordActions([
                ViewAction::make()->iconButton()->tooltip('Lihat')->color('info'),
            ])
            ->toolbarActions([
                Action::make('create')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->iconButton()
                    ->tooltip('Buat Lowongan Baru')
                    ->url(static::getUrl('create')),

                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RegistrationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCasualPositionOpenings::route('/'),
            'create' => CreateCasualPositionOpening::route('/create'),
            'view' => ViewCasualPositionOpening::route('/{record}'),
            'edit' => EditCasualPositionOpening::route('/{record}/edit'),
        ];
    }
}
