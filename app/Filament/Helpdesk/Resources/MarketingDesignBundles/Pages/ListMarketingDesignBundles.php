<?php

namespace App\Filament\Helpdesk\Resources\MarketingDesignBundles\Pages;

use App\Actions\BrandMarketing\DesignMaster\CreateDesignBundleAction;
use App\Enums\MarketingDesignBundleCategory;
use App\Enums\MarketingDesignBundleStatus;
use App\Filament\Helpdesk\Resources\MarketingDesignBundles\MarketingDesignBundleResource;
use App\Models\MarketingDesignBundle;
use App\Models\RndProjectMarketingMaterial;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Master Design index in the Project R&D list style (PRD §17.2): header card, filter bar,
 * Aktif/Diarsipkan tabs, row list, and numbered pagination. Every query starts from the
 * Resource's visibility scope, so a tampered filter never reveals archived bundles to Purchasing.
 */
class ListMarketingDesignBundles extends ListRecords
{
    protected static string $resource = MarketingDesignBundleResource::class;

    protected string $view = 'filament.helpdesk.marketing-design-bundles.index';

    private const int PER_PAGE = 20;

    public string $search = '';

    public string $brandFilter = '';

    public string $categoryFilter = '';

    public string $itemTypeFilter = '';

    public string $natureFilter = '';

    public string $statusView = 'active';

    public int $bundlesPage = 1;

    public function bundles(): LengthAwarePaginator
    {
        $bundles = $this->filteredQuery()
            ->with('brand')
            ->withCount('items')
            ->latest('updated_at')
            ->latest('id')
            ->paginate(self::PER_PAGE, ['*'], 'page', $this->bundlesPage);

        if ($bundles->isEmpty() && $this->bundlesPage > 1) {
            $this->bundlesPage = max(1, $bundles->lastPage());

            return $this->bundles();
        }

        return $bundles;
    }

    public function createBundleAction(): Action
    {
        return Action::make('createBundle')
            ->label('Buat Bundle')
            ->icon('heroicon-o-plus')
            ->visible(fn (): bool => auth()->user()?->can('create', MarketingDesignBundle::class) ?? false)
            ->modalHeading('Buat Bundle Master Design')
            ->modalDescription('Item dan file desain ditambahkan di halaman detail setelah bundle tersimpan.')
            ->modalSubmitActionLabel('Buat Bundle')
            ->schema(MarketingDesignBundleResource::bundleFormSchema())
            ->action(function (array $data, Action $action): void {
                try {
                    $bundle = app(CreateDesignBundleAction::class)->execute(auth()->user(), $data);
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title('Bundle belum dapat dibuat')->body($exception->validator->errors()->first())->send();
                    $action->halt();

                    return;
                }

                Notification::make()->success()->title('Bundle berhasil dibuat')->send();
                $this->redirect(MarketingDesignBundleResource::getUrl('view', ['record' => $bundle]));
            });
    }

    public function isInternal(): bool
    {
        return MarketingDesignBundleResource::viewerIsInternal();
    }

    /** @return array<int, string> */
    public function brandOptions(): array
    {
        return MarketingDesignBundleResource::brandOptions();
    }

    /** @return array<string, string> */
    public function categoryOptions(): array
    {
        return collect(MarketingDesignBundleCategory::cases())->mapWithKeys(fn (MarketingDesignBundleCategory $category): array => [$category->value => $category->getLabel()])->all();
    }

    /** @return array<string, string> */
    public function itemTypeOptions(): array
    {
        return RndProjectMarketingMaterial::TYPES;
    }

    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->brandFilter !== '' || $this->categoryFilter !== '' || $this->itemTypeFilter !== '' || $this->natureFilter !== '';
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'brandFilter', 'categoryFilter', 'itemTypeFilter', 'natureFilter', 'bundlesPage']);
    }

    public function showActive(): void
    {
        $this->statusView = 'active';
        $this->bundlesPage = 1;
    }

    public function showArchived(): void
    {
        $this->statusView = $this->isInternal() ? 'archived' : 'active';
        $this->bundlesPage = 1;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'brandFilter', 'categoryFilter', 'itemTypeFilter', 'natureFilter'], true)) {
            $this->bundlesPage = 1;
        }
    }

    public function previousBundlesPage(): void
    {
        $this->bundlesPage = max(1, $this->bundlesPage - 1);
    }

    public function nextBundlesPage(): void
    {
        $this->bundlesPage = min($this->bundlesPage + 1, $this->lastPage());
    }

    public function goToBundlesPage(int $page): void
    {
        $this->bundlesPage = max(1, min($page, $this->lastPage()));
    }

    private function lastPage(): int
    {
        return max(1, (int) ceil($this->filteredQuery()->count() / self::PER_PAGE));
    }

    private function filteredQuery(): Builder
    {
        $status = $this->statusView === 'archived' && $this->isInternal()
            ? MarketingDesignBundleStatus::Archived
            : MarketingDesignBundleStatus::Active;
        $search = trim($this->search);

        return MarketingDesignBundleResource::getEloquentQuery()
            ->where('status', $status)
            ->when($search !== '', fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query
                ->where('code', 'like', "%{$search}%")
                ->orWhere('normalized_code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")))
            ->when($this->brandFilter !== '', fn (Builder $query): Builder => $query->where('brand_id', (int) $this->brandFilter))
            ->when(MarketingDesignBundleCategory::tryFrom($this->categoryFilter), fn (Builder $query, MarketingDesignBundleCategory $category): Builder => $query->where('category', $category))
            ->when(array_key_exists($this->itemTypeFilter, RndProjectMarketingMaterial::TYPES), fn (Builder $query): Builder => $query
                ->whereHas('items', fn (Builder $items): Builder => $items->where('type', $this->itemTypeFilter)))
            ->when($this->natureFilter === 'physical', fn (Builder $query): Builder => $query
                ->whereHas('items', fn (Builder $items): Builder => $items->whereIn('type', RndProjectMarketingMaterial::PHYSICAL_TYPES)))
            ->when($this->natureFilter === 'digital', fn (Builder $query): Builder => $query
                ->whereHas('items', fn (Builder $items): Builder => $items->whereNotIn('type', RndProjectMarketingMaterial::PHYSICAL_TYPES)));
    }
}
