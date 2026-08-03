<?php

namespace App\Filament\Helpdesk\Resources\ErpRepairRequests;

use App\Enums\ItRequestStatus;
use App\Filament\Exports\ErpRepairRequestExporter;
use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\ErpRepairRequests\Pages\EditErpRepairRequest;
use App\Filament\Helpdesk\Resources\ErpRepairRequests\Pages\ListErpRepairRequests;
use App\Filament\Helpdesk\Resources\ErpRepairRequests\Pages\ViewErpRepairRequest;
use App\Models\ErpModule;
use App\Models\ErpRepairRequest;
use App\Models\ItRequestType;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
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
                    TextEntry::make('assignee.name')->label('Dikerjakan oleh')->placeholder('Belum ditugaskan'),
                    TextEntry::make('created_at')->label('Tanggal Pengajuan')->dateTime('d M Y H:i'),
                    TextEntry::make('work_classification')->label('Classification')->badge()->placeholder('Not classified'),
                    TextEntry::make('priority')->label('Priority')->badge(),
                    TextEntry::make('due_at')->label('Due Date')->dateTime('d M Y H:i')->placeholder('-'),
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

            Section::make('IT Follow-up')->schema([
                TextEntry::make('it_notes')->label('IT Notes')->placeholder('-')->columnSpanFull(),
                TextEntry::make('escalation_target')->label('Escalation Target')->placeholder('-'),
                TextEntry::make('escalation_reason')->label('Escalation Reason')->placeholder('-'),
                TextEntry::make('resolution_note')->label('Resolution')->placeholder('-')->columnSpanFull(),
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
            Section::make('Triage & Assignment')->schema([
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

                Select::make('assignee_id')
                    ->label('Ditugaskan ke')
                    ->options(User::role(['IT_STAFF', 'SUPERADMIN'])->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->nullable(),

                Select::make('work_classification')
                    ->label('Classification')
                    ->options(['standard' => 'Standard', 'major_project' => 'Major Project'])
                    ->required(),

                Select::make('priority')
                    ->label('Priority')
                    ->options(['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'])
                    ->required(),

                DateTimePicker::make('due_at')->label('Due Date')->seconds(false),
                Textarea::make('it_notes')->label('IT Notes')->rows(4)->columnSpanFull(),
            ])->columns(2),

            Section::make('Escalation')->schema([
                Select::make('escalation_target')
                    ->label('Escalation Target')
                    ->options([
                        'it_level_2' => 'IT Level 2',
                        'developer' => 'Developer',
                        'vendor' => 'Vendor',
                        'other' => 'Other',
                    ])
                    ->required(fn (Get $get): bool => self::statusValue($get('status')) === ItRequestStatus::Escalated->value),
                Textarea::make('escalation_reason')
                    ->label('Escalation Reason')
                    ->rows(3)
                    ->required(fn (Get $get): bool => self::statusValue($get('status')) === ItRequestStatus::Escalated->value)
                    ->columnSpanFull(),
            ]),

            Section::make('Resolution')->schema([
                Textarea::make('resolution_note')
                    ->label('Resolution Note')
                    ->rows(4)
                    ->required(fn (Get $get): bool => self::statusValue($get('status')) === ItRequestStatus::Completed->value)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ticket_number')
                    ->label('Ticket')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('requester.name')
                    ->label('Pemohon')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('branch.name')
                    ->label('Cabang')
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('module.name')
                    ->label('Modul ERP')
                    ->badge()
                    ->color('info')
                    ->placeholder('-'),

                TextColumn::make('requestType.name')
                    ->label('Type')
                    ->badge()
                    ->placeholder('-'),

                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->limit(50)
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ItRequestStatus $state) => $state->getLabel())
                    ->color(fn (ItRequestStatus $state) => $state->getColor()),

                TextColumn::make('work_classification')
                    ->label('Class')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'major_project' => 'Major Project',
                        'standard' => 'Standard',
                        default => 'Unclassified',
                    }),

                TextColumn::make('priority')
                    ->label('Priority')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'critical' => 'danger',
                        'high' => 'warning',
                        'low' => 'gray',
                        default => 'info',
                    }),

                TextColumn::make('assignee.name')
                    ->label('PIC')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(ItRequestStatus::class),

                SelectFilter::make('request_type_id')
                    ->label('Request Type')
                    ->options(ItRequestType::where('is_active', true)->orderBy('sort_order')->pluck('name', 'id')),

                SelectFilter::make('work_classification')
                    ->label('Classification')
                    ->options(['standard' => 'Standard', 'major_project' => 'Major Project']),

                SelectFilter::make('priority')
                    ->label('Priority')
                    ->options(['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical']),

                SelectFilter::make('erp_module_id')
                    ->label('Modul ERP')
                    ->options(ErpModule::where('is_active', true)->orderBy('sort_order')->pluck('name', 'id')),
            ])
            ->recordActions([
                ViewAction::make()->iconButton()->tooltip('Lihat'),
                EditAction::make()->iconButton()->tooltip('Edit'),
                DeleteAction::make()->iconButton(),
            ])
            ->toolbarActions([
                ExportAction::make()->icon('heroicon-o-arrow-down-tray')->color('success')->iconButton()->tooltip('Export Excel')->exporter(ErpRepairRequestExporter::class),

                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
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
        return parent::getEloquentQuery()->with(['requester', 'assignee', 'branch', 'module', 'requestType']);
    }
}
