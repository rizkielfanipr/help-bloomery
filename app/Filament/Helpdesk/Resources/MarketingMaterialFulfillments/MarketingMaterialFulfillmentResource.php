<?php

namespace App\Filament\Helpdesk\Resources\MarketingMaterialFulfillments;

use App\Enums\MarketingMaterialFulfillmentStatus;
use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\MarketingMaterialFulfillments\Pages\ListMarketingMaterialFulfillments;
use App\Filament\Helpdesk\Resources\MarketingMaterialFulfillments\Pages\ListMarketingMaterialFulfillmentsToReceive;
use App\Models\Location;
use App\Models\RndProjectMarketingMaterial;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class MarketingMaterialFulfillmentResource extends Resource
{
    use HasPermissions;

    protected static string $permissionPrefix = 'marketing material fulfillments';

    protected static ?string $model = RndProjectMarketingMaterial::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|UnitEnum|null $navigationGroup = 'Purchasing';

    protected static ?string $navigationLabel = 'Proses Material Marketing';

    protected static ?string $modelLabel = 'Proses Material Marketing';

    protected static ?string $pluralModelLabel = 'Proses Material Marketing';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.project.name')
                    ->label('PROJECT')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('product.name')
                    ->label('PRODUK')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('title')
                    ->label('MATERIAL')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('type')
                    ->label('TIPE')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state): string => RndProjectMarketingMaterial::TYPES[$state] ?? $state),

                TextColumn::make('fulfillment_status')
                    ->label('STATUS')
                    ->badge()
                    ->state(fn (RndProjectMarketingMaterial $record): MarketingMaterialFulfillmentStatus => $record->fulfillment?->status ?? MarketingMaterialFulfillmentStatus::NotStarted),

                TextColumn::make('created_at')
                    ->label('TANGGAL REQUEST')
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('project_name')
                    ->label('PROJECT')
                    ->form([TextInput::make('value')->label('Project')])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(filled($data['value'] ?? null), fn (Builder $query): Builder => $query
                            ->whereHas('product.project', fn (Builder $query) => $query
                                ->where('name', 'like', '%'.trim((string) $data['value']).'%')))),

                Filter::make('product_name_filter')
                    ->label('PRODUK')
                    ->form([TextInput::make('value')->label('Produk')])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(filled($data['value'] ?? null), fn (Builder $query): Builder => $query
                            ->whereHas('product', fn (Builder $query) => $query
                                ->where('name', 'like', '%'.trim((string) $data['value']).'%')))),

                Filter::make('material_name')
                    ->label('MATERIAL')
                    ->form([TextInput::make('value')->label('Material')])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(filled($data['value'] ?? null), fn (Builder $query): Builder => $query
                            ->where('title', 'like', '%'.trim((string) $data['value']).'%'))),

                SelectFilter::make('type')
                    ->label('TIPE')
                    ->options(array_intersect_key(
                        RndProjectMarketingMaterial::TYPES,
                        array_flip(RndProjectMarketingMaterial::PHYSICAL_TYPES),
                    )),

                SelectFilter::make('fulfillment_status')
                    ->label('STATUS')
                    ->options(MarketingMaterialFulfillmentStatus::class)
                    ->query(function (Builder $query, array $data): Builder {
                        $status = $data['value'] ?? null;

                        if ($status === MarketingMaterialFulfillmentStatus::NotStarted->value) {
                            return $query->where(function (Builder $query): void {
                                $query->whereDoesntHave('fulfillment')
                                    ->orWhereHas('fulfillment', fn (Builder $query) => $query
                                        ->where('status', MarketingMaterialFulfillmentStatus::NotStarted));
                            });
                        }

                        return $query->when(
                            filled($status),
                            fn (Builder $query): Builder => $query->whereHas(
                                'fulfillment',
                                fn (Builder $query): Builder => $query->where('status', $status),
                            ),
                        );
                    }),

                Filter::make('created_at')
                    ->label('TANGGAL REQUEST')
                    ->form([
                        DatePicker::make('from')->label('Dari Tanggal'),
                        DatePicker::make('until')->label('Sampai Tanggal'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date))),
            ], layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->defaultSort('updated_at', 'desc')
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25, 50, 100])
            ->recordActions([
                Action::make('view_detail')
                    ->label('Lihat Detail')
                    ->icon('heroicon-o-eye')
                    ->tooltip('Lihat Detail')
                    ->color('info')
                    ->iconButton()
                    ->modalWidth(Width::ThreeExtraLarge)
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->extraModalWindowAttributes(['class' => 'material-sourcing-modal'])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (RndProjectMarketingMaterial $record) => view('filament.helpdesk.marketing-material-fulfillments.view-detail', [
                        'record' => $record->load(['fulfillment.location.branch', 'fulfillment.orderedBy', 'fulfillment.receivedBy', 'creator']),
                    ])),

                Action::make('mark_ordered')
                    ->label('Tandai Dipesan')
                    ->icon('heroicon-o-shopping-cart')
                    ->tooltip('Tandai Dipesan')
                    ->color('warning')
                    ->iconButton()
                    ->modalWidth(Width::ThreeExtraLarge)
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->extraModalWindowAttributes(['class' => 'material-sourcing-modal'])
                    ->visible(fn (RndProjectMarketingMaterial $record): bool => auth()->user()?->can('process marketing material as purchasing')
                        && ($record->fulfillment?->status ?? MarketingMaterialFulfillmentStatus::NotStarted) === MarketingMaterialFulfillmentStatus::NotStarted)
                    ->form([
                        TextInput::make('vendor_name')->label('Nama Vendor/Percetakan')->required(),
                        DatePicker::make('order_date')->label('Tanggal Pesan')->required()->default(now()),
                        DatePicker::make('estimated_completion_date')->label('Estimasi Selesai'),
                        Textarea::make('purchasing_notes')->label('Catatan'),
                    ])
                    ->action(function (RndProjectMarketingMaterial $record, array $data): void {
                        $fulfillment = $record->fulfillment()->firstOrCreate([]);
                        $fulfillment->update([
                            ...$data,
                            'ordered_by' => auth()->id(),
                            'ordered_at' => now(),
                            'status' => MarketingMaterialFulfillmentStatus::Ordered,
                        ]);

                        Notification::make()->title('Material ditandai sudah dipesan')->success()->send();
                    }),

                Action::make('mark_received')
                    ->label('Tandai Diterima')
                    ->icon('heroicon-o-check-badge')
                    ->tooltip('Tandai Diterima')
                    ->color('success')
                    ->iconButton()
                    ->modalWidth(Width::ThreeExtraLarge)
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->extraModalWindowAttributes(['class' => 'material-sourcing-modal'])
                    ->visible(fn (RndProjectMarketingMaterial $record): bool => auth()->user()?->can('process marketing material as inventory')
                        && $record->fulfillment?->status === MarketingMaterialFulfillmentStatus::Ordered)
                    ->form([
                        TextInput::make('received_quantity')->label('Jumlah Diterima')->numeric()->integer()->required(),
                        DatePicker::make('received_date')->label('Tanggal Diterima')->required()->default(now()),
                        Select::make('location_id')
                            ->label('Lokasi Penyimpanan')
                            ->searchable()
                            ->options(fn (): array => Location::query()
                                ->with('branch')
                                ->orderBy('branch_id')
                                ->orderBy('code')
                                ->get()
                                ->mapWithKeys(fn (Location $location): array => [
                                    $location->id => "{$location->branch->name} — {$location->code} ({$location->name})",
                                ])
                                ->all()),
                        Textarea::make('inventory_notes')->label('Catatan'),
                    ])
                    ->action(function (RndProjectMarketingMaterial $record, array $data): void {
                        $record->fulfillment->update([
                            ...$data,
                            'received_by' => auth()->id(),
                            'received_at' => now(),
                            'status' => MarketingMaterialFulfillmentStatus::Received,
                        ]);

                        Notification::make()->title('Material ditandai sudah diterima & distok')->success()->send();
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('type', RndProjectMarketingMaterial::PHYSICAL_TYPES)
            ->with(['product.project', 'fulfillment']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarketingMaterialFulfillments::route('/'),
            'to-receive' => ListMarketingMaterialFulfillmentsToReceive::route('/diterima'),
        ];
    }
}
