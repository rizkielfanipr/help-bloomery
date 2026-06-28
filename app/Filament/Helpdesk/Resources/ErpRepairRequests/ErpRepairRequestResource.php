<?php

namespace App\Filament\Helpdesk\Resources\ErpRepairRequests;

use App\Enums\RequestStatus;
use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\ErpRepairRequests\Pages\EditErpRepairRequest;
use App\Filament\Helpdesk\Resources\ErpRepairRequests\Pages\ListErpRepairRequests;
use App\Filament\Helpdesk\Resources\ErpRepairRequests\Pages\ViewErpRepairRequest;
use App\Models\ErpModule;
use App\Models\ErpRepairRequest;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
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
        return (string) ErpRepairRequest::whereIn('status', [RequestStatus::Submitted, RequestStatus::InReview])->count() ?: null;
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
                    TextEntry::make('requester.name')->label('Pemohon'),
                    TextEntry::make('branch.name')->label('Cabang')->placeholder('-'),
                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (RequestStatus $state) => $state->getLabel())
                        ->color(fn (RequestStatus $state) => $state->getColor()),
                    TextEntry::make('module.name')->label('Modul ERP')->placeholder('-'),
                    TextEntry::make('assignee.name')->label('Dikerjakan oleh')->placeholder('Belum ditugaskan'),
                    TextEntry::make('created_at')->label('Tanggal Pengajuan')->dateTime('d M Y H:i'),
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
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                Select::make('status')
                    ->label('Status')
                    ->options(collect(RequestStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->getLabel()]))
                    ->required(),

                Select::make('assignee_id')
                    ->label('Ditugaskan ke')
                    ->options(User::all()->pluck('name', 'id'))
                    ->searchable()
                    ->nullable(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
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

                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->limit(50)
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (RequestStatus $state) => $state->getLabel())
                    ->color(fn (RequestStatus $state) => $state->getColor()),

                TextColumn::make('assignee.name')
                    ->label('PIC')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(RequestStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->getLabel()])),

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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['requester', 'assignee', 'branch', 'module']);
    }
}
