<?php

namespace App\Filament\Helpdesk\Resources\MaterialPricelists;

use App\Enums\MaterialPricingStatus;
use App\Filament\Helpdesk\Resources\MaterialPricelists\Pages\ListMaterialPricelists;
use App\Filament\Helpdesk\Resources\MaterialPricelists\Pages\ViewMaterialPricelist;
use App\Models\RndMaterialPricingRequest;
use App\Services\Rnd\Material\MaterialClassification;
use App\Services\TableFilterOptions;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Finance → Product Pricelist (docs/material-sourcing-lifecycle-prd.md §17): the official pricing
 * workflow queue. It is not Product Price Index (historical price analytics). Only Pricing
 * Requests created by the eligibility service appear here, so unapproved materials never do.
 */
class MaterialPricelistResource extends Resource
{
    protected static ?string $model = RndMaterialPricingRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Product Pricelist';

    protected static ?string $modelLabel = 'Product Pricelist';

    protected static ?string $pluralModelLabel = 'Product Pricelist';

    protected static ?string $slug = 'pricelist-bahan';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', RndMaterialPricingRequest::class) ?? false;
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->can('view', $record) ?? false;
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

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->select(['id', 'uuid', 'rnd_product_esb_material_id', 'product_detail_id', 'uom_name', 'origin', 'supplier_name', 'offer_price', 'price', 'company_code', 'branch_scope', 'status', 'revision', 'conflict_reason', 'queued_at', 'updated_at'])
                ->with('material:id,product_code,product_name,category_name,sub_category_name,material_section'))
            ->columns([
                TextColumn::make('material.product_code')->label('Kode')->fontFamily('mono')->weight('bold')
                    ->description(fn (RndMaterialPricingRequest $record): string => $record->material ? MaterialClassification::label($record->material) : '-'),
                TextColumn::make('material.product_name')->label('Bahan / WIP')->wrap(),
                TextColumn::make('material.category_name')->label('Kategori')->placeholder('-')->wrap()
                    ->description(fn (RndMaterialPricingRequest $record): ?string => $record->material?->sub_category_name),
                TextColumn::make('origin')->label('Asal')->badge()->color('gray')->formatStateUsing(fn (string $state): string => $state === RndMaterialPricingRequest::ORIGIN_WIP ? 'WIP' : 'Sourcing'),
                TextColumn::make('supplier_name')->label('Supplier Terpilih')->placeholder('-'),
                TextColumn::make('uom_name')->label('UOM Target')->description(fn (RndMaterialPricingRequest $record): string => 'Detail #'.$record->product_detail_id),
                TextColumn::make('offer_price')->label('Harga Penawaran (Ref.)')->numeric(0)->prefix('Rp ')->placeholder('-')->color('gray'),
                TextColumn::make('price')->label('Harga Pricelist')->numeric(0)->prefix('Rp ')->placeholder('Belum diisi')->weight('bold'),
                TextColumn::make('company_code')->label('Scope')->placeholder('-')->description(fn (RndMaterialPricingRequest $record): ?string => $record->branch_scope),
                TextColumn::make('status')->label('Status')->badge()
                    ->description(fn (RndMaterialPricingRequest $record): ?string => $record->conflict_reason ? 'Konflik perlu diselesaikan' : ($record->revision > 1 ? 'Revisi '.$record->revision : null)),
                TextColumn::make('queued_at')->label('Masuk Antrean')->dateTime('d M Y H:i', 'Asia/Jakarta')->sortable(),
                TextColumn::make('updated_at')->label('Diperbarui')->since()->sortable(),
            ])
            ->searchable(['material.product_code', 'material.product_name'])
            ->filters([
                Filter::make('search')->label('Kode / Nama')
                    ->schema([TextInput::make('value')->label('Kode / Nama')])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(filled($data['value'] ?? null), fn (Builder $query): Builder => $query
                        ->whereHas('material', fn (Builder $material) => $material
                            ->where('product_code', 'like', '%'.trim((string) $data['value']).'%')
                            ->orWhere('product_name', 'like', '%'.trim((string) $data['value']).'%')))),
                SelectFilter::make('status')->label('Status Pricelist')->options(MaterialPricingStatus::options()),
                SelectFilter::make('origin')->label('Asal')->options([RndMaterialPricingRequest::ORIGIN_SOURCING => 'Sourcing', RndMaterialPricingRequest::ORIGIN_WIP => 'WIP']),
                Filter::make('section')->label('Jenis')
                    ->schema([Select::make('value')->label('Jenis')->options(MaterialClassification::options())])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'wip' => $query->where('origin', RndMaterialPricingRequest::ORIGIN_WIP),
                        'marketing' => $query->whereHas('material', fn (Builder $material) => $material->where('material_section', 'marketing')->orWhere('category_name', 'like', '%marketing%')),
                        'packaging' => $query->whereHas('material', fn (Builder $material) => $material->where('category_name', 'like', '%packaging%')->orWhere('category_name', 'like', '%kemasan%')),
                        'raw' => $query->where('origin', RndMaterialPricingRequest::ORIGIN_SOURCING)->whereHas('material', fn (Builder $material) => $material->where('category_name', 'not like', '%packaging%')->where('category_name', 'not like', '%kemasan%')->where(fn (Builder $material) => $material->whereNull('material_section')->orWhere('material_section', '!=', 'marketing'))),
                        default => $query,
                    }),
                SelectFilter::make('category')->label('Kategori')
                    ->options(fn (): array => app(TableFilterOptions::class)->materialPricelistCategories())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null) ? $query->whereHas('material', fn (Builder $material) => $material->where('category_name', $data['value'])) : $query),
                SelectFilter::make('supplier_name')->label('Supplier')
                    ->options(fn (): array => app(TableFilterOptions::class)->materialPricelistSuppliers()),
                SelectFilter::make('company_code')->label('Company')->options(fn (): array => self::companyFilterOptions()),
                Filter::make('queued_at')->label('Masuk Antrean')
                    ->schema([DatePicker::make('from')->label('Dari Tanggal'), DatePicker::make('until')->label('Sampai Tanggal')])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->where('queued_at', '>=', $date.' 00:00:00'))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->where('queued_at', '<=', $date.' 23:59:59'))),
            ], layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->recordActions([ViewAction::make()->label('Proses')])
            ->defaultSort('queued_at', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Tidak ada Pricelist yang menunggu Finance')
            ->emptyStateDescription('Bahan masuk setelah sampel disetujui R&D; WIP masuk setelah sinkronisasi ESB terverifikasi.');
    }

    /**
     * Statuses Finance still has to act on (the "Needs Action" tab).
     *
     * @var list<string>
     */
    public const OPEN_STATUSES = [
        MaterialPricingStatus::ReadyForPricing->value,
        MaterialPricingStatus::DraftPricing->value,
        MaterialPricingStatus::VerificationRequired->value,
        MaterialPricingStatus::Failed->value,
    ];

    /**
     * @return array<string, string>
     */
    public static function companyFilterOptions(): array
    {
        return collect(array_keys((array) config('esb.core.companies', [])))->mapWithKeys(fn (string $code): array => [$code => $code])->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaterialPricelists::route('/'),
            'view' => ViewMaterialPricelist::route('/{record}'),
        ];
    }
}
