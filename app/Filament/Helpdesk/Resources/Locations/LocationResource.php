<?php

namespace App\Filament\Helpdesk\Resources\Locations;

use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\Locations\Pages\CreateLocation;
use App\Filament\Helpdesk\Resources\Locations\Pages\EditLocation;
use App\Filament\Helpdesk\Resources\Locations\Pages\ListLocations;
use App\Filament\Helpdesk\Resources\Locations\Pages\LocationFloorPlanPage;
use App\Models\Branch;
use App\Models\Location;
use App\Models\LocationType;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use UnitEnum;

class LocationResource extends Resource
{
    use HasPermissions;

    protected static string $permissionPrefix = 'locations';

    protected static ?string $model = Location::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map';

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Lokasi';

    protected static ?string $pluralModelLabel = 'Lokasi';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Lokasi')
                    ->schema([
                        Select::make('branch_id')
                            ->label('Cabang')
                            ->options(Branch::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('parent_id', null)),

                        Select::make('parent_id')
                            ->label('Induk Lokasi')
                            ->searchable()
                            ->placeholder('— Tidak ada (lokasi root) —')
                            ->options(function (Get $get, ?Location $record): array {
                                $branchId = $get('branch_id');

                                if (! $branchId) {
                                    return [];
                                }

                                $query = Location::query()->where('branch_id', $branchId);

                                if ($record) {
                                    $excludedIds = [
                                        $record->id,
                                        ...Location::query()
                                            ->where('branch_id', $branchId)
                                            ->where('code', 'like', $record->code.'-%')
                                            ->pluck('id'),
                                    ];

                                    $query->whereNotIn('id', $excludedIds);
                                }

                                return $query->orderBy('name')->pluck('name', 'id')->all();
                            }),

                        TextInput::make('name')
                            ->label('Nama Lokasi')
                            ->required()
                            ->maxLength(255),

                        Select::make('type')
                            ->label('Tipe')
                            ->options(fn (): array => LocationType::query()
                                ->where('is_active', true)
                                ->orderBy('sort_order')
                                ->pluck('name', 'name')
                                ->all())
                            ->searchable()
                            ->required(),

                        TextInput::make('segment')
                            ->label('Kode Segmen')
                            ->required()
                            ->maxLength(20)
                            ->rule('regex:/^[A-Za-z0-9]+$/')
                            ->helperText('Huruf dan angka saja, tanpa spasi atau simbol. Kode lengkap lokasi digabung otomatis dari induk + segmen ini.'),

                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),

                        Textarea::make('notes')
                            ->label('Catatan')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Tipe')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('branch.name')
                    ->label('Cabang')
                    ->sortable(),

                TextColumn::make('parent.name')
                    ->label('Induk')
                    ->placeholder('—'),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->recordActions([
                Action::make('floor_plan')
                    ->label('Denah')
                    ->iconButton()
                    ->icon('heroicon-o-squares-2x2')
                    ->tooltip('Lihat Denah')
                    ->url(fn (Location $record): string => LocationFloorPlanPage::getUrl(['branch_id' => $record->branch_id])),
                Action::make('print_label')
                    ->label('Cetak Label')
                    ->iconButton()
                    ->icon('heroicon-o-qr-code')
                    ->tooltip('Cetak Label')
                    ->url(fn (Location $record): string => route('helpdesk.locations.label-pdf', $record))
                    ->openUrlInNewTab(),
                EditAction::make()->iconButton()->tooltip('Edit'),
                DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Hapus')
                    ->requiresConfirmation()
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->modalHeading('Hapus Lokasi?')
                    ->modalDescription('Lokasi ini akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.')
                    ->visible(fn (Location $record): bool => ! $record->children()->exists()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('print_labels')
                        ->label('Cetak Label')
                        ->icon('heroicon-o-qr-code')
                        ->action(fn (Collection $records) => redirect()->route('helpdesk.locations.labels-pdf', [
                            'ids' => $records->pluck('id')->all(),
                        ])),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['branch', 'parent']);
        $user = auth()->user();

        if ($user && ! $user->canAccessAllBranches()) {
            $query->whereIn('branch_id', $user->accessibleBranchIds());
        }

        return $query;
    }

    public static function canView($record): bool
    {
        $user = auth()->user();

        return parent::canView($record)
            && $user?->canAccessBranch($record->branch_id);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLocations::route('/'),
            'create' => CreateLocation::route('/create'),
            'edit' => EditLocation::route('/{record}/edit'),
            'floor-plan' => LocationFloorPlanPage::route('/floor-plan'),
        ];
    }
}
