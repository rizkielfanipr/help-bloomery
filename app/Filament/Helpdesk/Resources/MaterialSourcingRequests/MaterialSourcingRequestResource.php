<?php

namespace App\Filament\Helpdesk\Resources\MaterialSourcingRequests;

use App\Enums\MaterialSourcingRequestStatus;
use App\Filament\Helpdesk\Resources\MaterialSourcingRequests\Pages\ListMaterialSourcingRequests;
use App\Filament\Helpdesk\Resources\MaterialSourcingRequests\Pages\ViewMaterialSourcingRequest;
use App\Filament\Helpdesk\Resources\MaterialSourcings\MaterialSourcingResource;
use App\Models\MaterialSourcingRequest;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Purchasing → Permintaan Sourcing (docs/products/purchasing/employee-sourcing-request/prd.md §17.5).
 * Requests are read-only here; every decision runs through an Action from the detail page.
 */
class MaterialSourcingRequestResource extends Resource
{
    protected static ?string $model = MaterialSourcingRequest::class;

    protected static ?string $slug = 'material-sourcing-requests';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Purchasing';

    protected static ?string $navigationLabel = 'Permintaan Sourcing';

    protected static ?string $modelLabel = 'Permintaan Sourcing';

    protected static ?string $pluralModelLabel = 'Permintaan Sourcing';

    protected static ?string $recordTitleAttribute = 'request_number';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', MaterialSourcingRequest::class) ?? false;
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof MaterialSourcingRequest && (auth()->user()?->can('view', $record) ?? false);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('company_code', MaterialSourcingRequest::COMPANY_CODE);
    }

    public static function actionableCount(): int
    {
        return MaterialSourcingRequest::query()->actionable()->count();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::actionableCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Requester')->columns(3)->schema([
                TextEntry::make('request_number')->label('Nomor')->copyable(),
                TextEntry::make('status')->label('Status')->badge(),
                TextEntry::make('submitted_at')->label('Dikirim')->dateTime('d M Y H:i')->since(),
                TextEntry::make('requester_name')->label('Requester'),
                TextEntry::make('branch_name')->label('Cabang / Divisi'),
                TextEntry::make('assignee.name')->label('PIC Purchasing')->placeholder('Belum diproses'),
            ]),
            Section::make('Produk')->columns(3)->schema([
                TextEntry::make('product_name')->label('Nama Produk')->columnSpan(2),
                TextEntry::make('product_code')->label('Kode')->copyable(),
                TextEntry::make('category_name')->label('Kategori')->placeholder('-'),
                TextEntry::make('sub_category_name')->label('Subkategori')->placeholder('-'),
                TextEntry::make('uom_name')->label('UOM'),
            ]),
            Section::make('Kebutuhan')->columns(3)->schema([
                TextEntry::make('reason')->label('Alasan / Tujuan')->columnSpanFull(),
                TextEntry::make('estimated_quantity')->label('Estimasi Kebutuhan')->placeholder('-')
                    ->state(fn (MaterialSourcingRequest $record): ?string => $record->formattedQuantity()),
                TextEntry::make('target_date')->label('Target Tanggal')->date('d M Y')->placeholder('-'),
                TextEntry::make('notes')->label('Catatan')->placeholder('-')->columnSpanFull(),
                View::make('filament.helpdesk.material-sourcing-requests.attachments')->columnSpanFull(),
            ]),
            Section::make('Keputusan Terakhir')->columns(3)
                ->visible(fn (MaterialSourcingRequest $record): bool => filled($record->last_decision_reason))
                ->schema([
                    TextEntry::make('last_decision_reason')->label('Alasan')->columnSpan(2),
                    TextEntry::make('lastDecisionBy.name')->label('Oleh')->placeholder('-')
                        ->helperText(fn (MaterialSourcingRequest $record): ?string => $record->last_decision_at?->format('d M Y H:i')),
                ]),
            Section::make('Sourcing Hasil Request')->columns(3)
                ->visible(fn (MaterialSourcingRequest $record): bool => $record->rnd_product_esb_material_id !== null)
                ->schema([
                    TextEntry::make('sourcingMaterial.product_code')->label('Referensi Sourcing')->copyable()
                        ->url(fn (): ?string => auth()->user()?->can('view material sourcings') ? MaterialSourcingResource::getUrl('index') : null),
                    TextEntry::make('sourcingMaterial.sourcing_status')->label('Status Sourcing')->badge(),
                    TextEntry::make('converted_at')->label('Dibuat')->dateTime('d M Y H:i'),
                ]),
            Section::make('Riwayat Status')->schema([
                RepeatableEntry::make('statusHistories')->hiddenLabel()->columns(4)->schema([
                    TextEntry::make('to_status')->label('Status')->badge(),
                    TextEntry::make('actor.name')->label('Oleh')->placeholder('Sistem'),
                    TextEntry::make('created_at')->label('Waktu')->dateTime('d M Y H:i'),
                    TextEntry::make('reason')->label('Alasan')->placeholder('-'),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->recordTitleAttribute('request_number')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['assignee:id,name']))
            ->columns([
                TextColumn::make('request_number')->label('Nomor')->searchable()->copyable()->sortable(),
                TextColumn::make('requester_name')->label('Requester / Cabang')->searchable(['requester_name', 'branch_name'])->wrap()
                    ->description(fn (MaterialSourcingRequest $record): string => $record->branch_name),
                TextColumn::make('product_name')->label('Produk')->searchable(['product_name', 'product_code'])->wrap()
                    ->description(fn (MaterialSourcingRequest $record): string => $record->product_code.' · '.$record->uom_name),
                TextColumn::make('target_date')->label('Target')->date('d M Y')->placeholder('-')->sortable(),
                TextColumn::make('status')->label('Status / PIC')->badge()
                    ->description(fn (MaterialSourcingRequest $record): string => 'PIC: '.($record->assignee?->name ?? '-')),
                TextColumn::make('submitted_at')->label('Umur')->since()->sortable()
                    ->tooltip(fn (MaterialSourcingRequest $record): ?string => $record->submitted_at?->format('d M Y H:i')),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->multiple()
                    ->options(collect(MaterialSourcingRequestStatus::cases())->mapWithKeys(fn (MaterialSourcingRequestStatus $status): array => [$status->value => $status->getLabel()])->all())
                    ->default(MaterialSourcingRequestStatus::actionableValues()),
                SelectFilter::make('branch_id')->label('Cabang')->relationship('branch', 'name')->searchable()->preload(),
                SelectFilter::make('requester_id')->label('Requester')->relationship('requester', 'name')->searchable(),
                SelectFilter::make('assigned_to')->label('PIC')->relationship('assignee', 'name')->searchable(),
                Filter::make('product')->label('Produk / Kode')
                    ->schema([TextInput::make('value')->label('Produk / Kode')])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(filled($data['value'] ?? null), fn (Builder $query): Builder => $query
                        ->where(fn (Builder $query) => $query
                            ->where('product_name', 'like', '%'.trim((string) $data['value']).'%')
                            ->orWhere('product_code', 'like', '%'.trim((string) $data['value']).'%')))),
                self::dateRangeFilter('submitted_at', 'Tanggal Submit'),
                self::dateRangeFilter('target_date', 'Target Tanggal'),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('submitted_at')
            ->emptyStateHeading('Tidak ada Permintaan Sourcing')
            ->emptyStateDescription('Request baru dari Employee App akan muncul di sini.');
    }

    private static function dateRangeFilter(string $column, string $label): Filter
    {
        return Filter::make($column)
            ->label($label)
            ->schema([
                DatePicker::make('from')->label($label.' Dari'),
                DatePicker::make('until')->label($label.' Sampai'),
            ])
            ->query(fn (Builder $query, array $data): Builder => $query
                ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate($column, '>=', $date))
                ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate($column, '<=', $date)));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaterialSourcingRequests::route('/'),
            'view' => ViewMaterialSourcingRequest::route('/{record}'),
        ];
    }
}
