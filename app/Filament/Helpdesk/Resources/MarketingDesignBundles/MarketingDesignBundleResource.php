<?php

namespace App\Filament\Helpdesk\Resources\MarketingDesignBundles;

use App\Enums\MarketingDesignBundleCategory;
use App\Filament\Helpdesk\Resources\MarketingDesignBundles\Pages\ListMarketingDesignBundles;
use App\Filament\Helpdesk\Resources\MarketingDesignBundles\Pages\ViewMarketingDesignBundle;
use App\Models\Brand;
use App\Models\MarketingDesignBundle;
use App\Policies\MarketingDesignBundlePolicy;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
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

        return $user !== null && app(MarketingDesignBundlePolicy::class)->viewArchived($user);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarketingDesignBundles::route('/'),
            'view' => ViewMarketingDesignBundle::route('/{record}'),
        ];
    }
}
