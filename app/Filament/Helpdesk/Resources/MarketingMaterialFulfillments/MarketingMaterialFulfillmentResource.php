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
                    ->label('Project')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('product.name')
                    ->label('Produk')
                    ->searchable(),

                TextColumn::make('title')
                    ->label('Material')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('type')
                    ->label('Tipe')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state): string => RndProjectMarketingMaterial::TYPES[$state] ?? $state),

                TextColumn::make('fulfillment_status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (RndProjectMarketingMaterial $record): MarketingMaterialFulfillmentStatus => $record->fulfillment?->status ?? MarketingMaterialFulfillmentStatus::NotStarted),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                Action::make('view_detail')
                    ->label('Lihat Detail')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalWidth(Width::TwoExtraLarge)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (RndProjectMarketingMaterial $record) => view('filament.helpdesk.marketing-material-fulfillments.view-detail', [
                        'record' => $record->load(['fulfillment.location.branch', 'fulfillment.orderedBy', 'fulfillment.receivedBy', 'creator']),
                    ])),

                Action::make('mark_ordered')
                    ->label('Tandai Dipesan')
                    ->icon('heroicon-o-shopping-cart')
                    ->color('warning')
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
                    ->color('success')
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
