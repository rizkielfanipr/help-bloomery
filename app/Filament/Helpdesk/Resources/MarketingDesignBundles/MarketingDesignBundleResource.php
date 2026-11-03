<?php

namespace App\Filament\Helpdesk\Resources\MarketingDesignBundles;

use App\Enums\MarketingDesignBundleCategory;
use App\Enums\MarketingDesignBundleStatus;
use App\Enums\MarketingDesignVersionStatus;
use App\Filament\Helpdesk\Resources\MarketingDesignBundles\Pages\ListMarketingDesignBundles;
use App\Filament\Helpdesk\Resources\MarketingDesignBundles\Pages\ViewMarketingDesignBundle;
use App\Models\Brand;
use App\Models\MarketingDesignBundle;
use App\Models\MarketingDesignBundleVersion;
use App\Models\RndProjectMarketingMaterial;
use App\Policies\MarketingDesignBundlePolicy;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
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
 * Canonical Master Design page, shared by Brand Marketing and Purchasing (PRD §17.1). The sidebar
 * lives in the custom layout, and access follows the Policies rather than the navigation.
 */
class MarketingDesignBundleResource extends Resource
{
    protected static ?string $model = MarketingDesignBundle::class;

    protected static ?string $slug = 'marketing-design-bundles';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static string|UnitEnum|null $navigationGroup = 'Brand Marketing';

    protected static ?string $navigationLabel = 'Master Design';

    protected static ?string $modelLabel = 'Master Design';

    protected static ?string $pluralModelLabel = 'Master Design';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', MarketingDesignBundle::class) ?? false;
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof MarketingDesignBundle && (auth()->user()?->can('view', $record) ?? false);
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
        $user = auth()->user();

        return $user === null
            ? parent::getEloquentQuery()->whereRaw('1 = 0')
            : parent::getEloquentQuery()->visibleTo($user);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'brand',
                'publishedVersion' => fn ($version) => $version->withCount('items'),
                'latestVersion' => fn ($version) => $version->withCount('items'),
            ]))
            ->columns([
                TextColumn::make('brand.name')->label('BRAND')->sortable()->wrap(),
                TextColumn::make('code')
                    ->label('BUNDLE')
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (MarketingDesignBundle $record): string => $record->name)
                    ->wrap(),
                TextColumn::make('category')->label('KATEGORI')->badge()->color('gray'),
                TextColumn::make('published_version')
                    ->label('VERSI PUBLISHED')
                    ->state(fn (MarketingDesignBundle $record): string => $record->publishedVersion?->label() ?? 'Belum ada'),
                TextColumn::make('items_total')
                    ->label('JUMLAH ITEM')
                    ->state(fn (MarketingDesignBundle $record): int => ($record->publishedVersion ?? $record->latestVersion)?->items_count ?? 0),
                TextColumn::make('status')->label('STATUS BUNDLE')->badge(),
                TextColumn::make('current_version_status')
                    ->label('STATUS VERSI')
                    ->badge()
                    ->state(fn (MarketingDesignBundle $record): ?MarketingDesignVersionStatus => self::viewerIsInternal()
                        ? $record->latestVersion?->status
                        : $record->publishedVersion?->status),
                TextColumn::make('published_at')
                    ->label('TANGGAL PUBLISH')
                    ->state(fn (MarketingDesignBundle $record): string => $record->publishedVersion?->published_at?->format('d M Y') ?? '—')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                        MarketingDesignBundleVersion::query()
                            ->select('published_at')
                            ->whereColumn('marketing_design_bundle_id', 'marketing_design_bundles.id')
                            ->where('status', MarketingDesignVersionStatus::Published)
                            ->limit(1),
                        $direction,
                    )),
                TextColumn::make('updated_at')->label('DIPERBARUI')->dateTime('d M Y, H:i')->sortable(),
            ])
            ->filters([
                Filter::make('search')
                    ->schema([TextInput::make('value')->label('Kode atau nama')])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query
                            ->where('code', 'like', '%'.trim((string) $data['value']).'%')
                            ->orWhere('normalized_code', 'like', '%'.trim((string) $data['value']).'%')
                            ->orWhere('name', 'like', '%'.trim((string) $data['value']).'%')),
                    )),
                SelectFilter::make('brand_id')->label('Brand')->placeholder('Semua Brand')->options(fn (): array => self::brandOptions()),
                SelectFilter::make('category')->label('Kategori')->options(MarketingDesignBundleCategory::class),
                SelectFilter::make('status')->label('Status Bundle')->options(MarketingDesignBundleStatus::class)->default(MarketingDesignBundleStatus::Active->value),
                SelectFilter::make('version_status')
                    ->label('Status Versi')
                    ->options(MarketingDesignVersionStatus::class)
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null) && self::viewerIsInternal(),
                        fn (Builder $query): Builder => $query->whereHas('versions', fn (Builder $versions): Builder => $versions->where('status', $data['value'])),
                    )),
                SelectFilter::make('item_type')
                    ->label('Tipe Item')
                    ->options(RndProjectMarketingMaterial::TYPES)
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $query): Builder => self::whereHasItem($query, fn (Builder $items): Builder => $items->where('type', $data['value'])),
                    )),
                SelectFilter::make('nature')
                    ->label('Fisik/Digital')
                    ->options(['physical' => 'Fisik', 'digital' => 'Digital'])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'physical' => self::whereHasItem($query, fn (Builder $items): Builder => $items->whereIn('type', RndProjectMarketingMaterial::PHYSICAL_TYPES)),
                        'digital' => self::whereHasItem($query, fn (Builder $items): Builder => $items->whereNotIn('type', RndProjectMarketingMaterial::PHYSICAL_TYPES)),
                        default => $query,
                    }),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->defaultSort('updated_at', 'desc')
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25, 50, 100])
            ->recordUrl(fn (MarketingDesignBundle $record): string => self::getUrl('view', ['record' => $record]))
            ->recordActions([ViewAction::make()->iconButton()->tooltip('Lihat Detail')])
            ->emptyStateHeading('Belum ada Master Design')
            ->emptyStateDescription('Bundle yang sesuai filter akan tampil di sini.');
    }

    /** @return array<int, mixed> */
    public static function bundleFormSchema(bool $brandLocked = false): array
    {
        return [
            Select::make('brand_id')->label('Brand')->options(fn (): array => self::brandOptions())->searchable()->required()
                ->disabled($brandLocked)->helperText($brandLocked ? 'Brand tidak dapat diubah setelah bundle dibuat.' : null),
            Grid::make(['default' => 1, 'sm' => 2])->schema([
                TextInput::make('code')->label('Kode Bundle')->required()->maxLength(100)->helperText('Unik dalam satu Brand, tanpa membedakan huruf besar dan kecil.'),
                Select::make('category')->label('Kategori')->options(MarketingDesignBundleCategory::class)->required()->native(false),
            ]),
            TextInput::make('name')->label('Nama Bundle')->required()->maxLength(255),
            Textarea::make('description')->label('Deskripsi')->rows(3)->maxLength(5000),
        ];
    }

    /** @return array<int, string> */
    public static function brandOptions(): array
    {
        return Brand::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    public static function viewerIsInternal(): bool
    {
        $user = auth()->user();

        return $user !== null && app(MarketingDesignBundlePolicy::class)->viewUnpublished($user);
    }

    /**
     * Item filters read the versions the viewer works with: the published one for non-internal
     * users, and every unsuperseded one for internal users.
     *
     * @param  callable(Builder): Builder  $constraint
     */
    private static function whereHasItem(Builder $query, callable $constraint): Builder
    {
        return $query->whereHas('versions', function (Builder $versions) use ($constraint): void {
            self::viewerIsInternal()
                ? $versions->where('status', '!=', MarketingDesignVersionStatus::Superseded)
                : $versions->where('status', MarketingDesignVersionStatus::Published);

            $versions->whereHas('items', $constraint);
        });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarketingDesignBundles::route('/'),
            'view' => ViewMarketingDesignBundle::route('/{record}'),
        ];
    }
}
