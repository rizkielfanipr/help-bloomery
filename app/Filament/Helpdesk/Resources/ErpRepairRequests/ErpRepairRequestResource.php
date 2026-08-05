<?php

namespace App\Filament\Helpdesk\Resources\ErpRepairRequests;

use App\Enums\ItRequestStatus;
use App\Filament\Exports\ErpRepairRequestExporter;
use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\ErpRepairRequests\Pages\EditErpRepairRequest;
use App\Filament\Helpdesk\Resources\ErpRepairRequests\Pages\ListErpRepairRequests;
use App\Filament\Helpdesk\Resources\ErpRepairRequests\Pages\ViewErpRepairRequest;
use App\Models\Branch;
use App\Models\ErpModule;
use App\Models\ErpRepairRequest;
use App\Models\ItRequestType;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ErpRepairRequestResource extends Resource
{
    use HasPermissions;

    protected static string $permissionPrefix = 'erp requests';

    protected static ?string $model = ErpRepairRequest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-server-stack';

    protected static string|UnitEnum|null $navigationGroup = 'Information Technology';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Permintaan ERP';

    protected static ?string $pluralModelLabel = 'Permintaan ERP';

    public static function getNavigationBadge(): ?string
    {
        return (string) ErpRepairRequest::whereIn('status', [ItRequestStatus::Submitted, ItRequestStatus::Review])->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi Permintaan')->schema([
                Grid::make(3)->schema([
                    TextEntry::make('ticket_number')->label('Ticket')->badge()->color('info'),
                    TextEntry::make('requester.name')->label('Pemohon'),
                    TextEntry::make('branch.name')->label('Cabang')->placeholder('-'),
                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (ItRequestStatus $state) => $state->getLabel())
                        ->color(fn (ItRequestStatus $state) => $state->getColor()),
                    TextEntry::make('requestType.name')->label('Request Type')->placeholder('-'),
                    TextEntry::make('module.name')->label('Modul ERP')->placeholder('-'),
                    TextEntry::make('created_at')->label('Tanggal Pengajuan')->dateTime('d M Y H:i'),
                    TextEntry::make('priority')->label('Priority')->badge(),
                ]),
            ]),

            Section::make('Keterangan Permintaan')->schema([
                TextEntry::make('keterangan')->label('')->columnSpanFull(),
            ]),

            Section::make('Lampiran')
                ->schema([
                    TextEntry::make('attachments')
                        ->label('')
                        ->state(fn (ErpRepairRequest $record): string => implode(', ', array_map(
                            fn ($path) => basename($path),
                            $record->attachments ?? []
                        )))
                        ->placeholder('Tidak ada lampiran'),
                ])
                ->hidden(fn (ErpRepairRequest $record): bool => empty($record->attachments)),

            Section::make()->schema([
                TextEntry::make('it_notes')->label('IT Notes')->placeholder('-')->columnSpanFull(),
            ]),

            Section::make('Activity Timeline')->schema([
                RepeatableEntry::make('activities')
                    ->label('')
                    ->schema([
                        TextEntry::make('created_at')->label('Time')->dateTime('d M Y H:i'),
                        TextEntry::make('actor.name')->label('Actor')->placeholder('System'),
                        TextEntry::make('action')->label('Action')->badge(),
                        TextEntry::make('notes')->label('Notes')->placeholder('-')->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Status')->schema([
                Select::make('status')
                    ->label('Status')
                    ->options(fn (?ErpRepairRequest $record): array => $record
                        ? collect([$record->status, ...$record->status->allowedTransitions()])
                            ->mapWithKeys(fn (ItRequestStatus $status): array => [$status->value => $status->getLabel()])
                            ->all()
                        : collect(ItRequestStatus::cases())
                            ->mapWithKeys(fn (ItRequestStatus $status): array => [$status->value => $status->getLabel()])
                            ->all())
                    ->live()
                    ->required(),

                Textarea::make('it_notes')
                    ->label('IT Notes')
                    ->rows(4)
                    ->required(fn (Get $get): bool => self::statusValue($get('status')) === ItRequestStatus::Rejected->value)
                    ->helperText('Wajib diisi jika status diubah menjadi Rejected.')
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ticket_number')
                    ->label('TIKET')
                    ->searchable()
                    ->copyable()
                    ->weight('semibold'),

                TextColumn::make('created_at')
                    ->label('TANGGAL')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('requester.name')
                    ->label('PEMOHON')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('branch.name')
                    ->label('CABANG')
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('module.name')
                    ->label('MODUL ERP')
                    ->badge()
                    ->color('info')
                    ->placeholder('-'),

                TextColumn::make('requestType.name')
                    ->label('TYPE')
                    ->badge()
                    ->placeholder('-'),

                TextColumn::make('keterangan')
                    ->label('KETERANGAN')
                    ->limit(55)
                    ->tooltip(fn (ErpRepairRequest $record): string => (string) $record->keterangan)
                    ->wrap(),

                TextColumn::make('status')
                    ->label('STATUS')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (ItRequestStatus $state) => $state->getLabel())
                    ->color(fn (ItRequestStatus $state) => $state->getColor()),

                TextColumn::make('priority')
                    ->label('PRIORITY')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'critical' => 'danger',
                        'high' => 'warning',
                        'low' => 'gray',
                        default => 'info',
                    }),
            ])
            ->filters([
                Filter::make('ticket_number')
                    ->form([TextInput::make('value')])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(filled($data['value'] ?? null), fn (Builder $query): Builder => $query
                            ->where('ticket_number', 'like', '%'.trim((string) $data['value']).'%'))),

                Filter::make('created_at')
                    ->form([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date))),

                Filter::make('requester_name')
                    ->form([TextInput::make('value')])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(filled($data['value'] ?? null), fn (Builder $query): Builder => $query
                            ->whereHas('requester', fn (Builder $query) => $query
                                ->where('name', 'like', '%'.trim((string) $data['value']).'%')))),

                Filter::make('keterangan')
                    ->form([TextInput::make('value')])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(filled($data['value'] ?? null), fn (Builder $query): Builder => $query
                            ->where('keterangan', 'like', '%'.trim((string) $data['value']).'%'))),

                SelectFilter::make('status')
                    ->label('STATUS')
                    ->options(ItRequestStatus::class),

                SelectFilter::make('branch_id')
                    ->label('CABANG')
                    ->options(Branch::orderBy('name')->pluck('name', 'id'))
                    ->searchable(),

                SelectFilter::make('request_type_id')
                    ->label('Request Type')
                    ->options(ItRequestType::where('is_active', true)->orderBy('sort_order')->pluck('name', 'id')),

                SelectFilter::make('priority')
                    ->label('Priority')
                    ->options(['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical']),

                SelectFilter::make('erp_module_id')
                    ->label('Modul ERP')
                    ->options(ErpModule::where('is_active', true)->orderBy('sort_order')->pluck('name', 'id')),
            ], layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->recordActions([
                ViewAction::make()->iconButton()->tooltip('Lihat Detail'),
                DeleteAction::make()->iconButton()->tooltip('Hapus'),
            ])
            ->toolbarActions([
                ExportAction::make()->icon('heroicon-o-arrow-down-tray')->color('success')->iconButton()->tooltip('Export Excel')->exporter(ErpRepairRequestExporter::class),

                DeleteBulkAction::make()
                    ->iconButton()
                    ->tooltip('Hapus data terpilih')
                    ->color('danger'),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListErpRepairRequests::route('/'),
            'view' => ViewErpRepairRequest::route('/{record}'),
            'edit' => EditErpRepairRequest::route('/{record}/edit'),
        ];
    }

    private static function statusValue(mixed $status): ?string
    {
        return $status instanceof ItRequestStatus ? $status->value : $status;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['requester', 'branch', 'module', 'requestType']);
    }
}
