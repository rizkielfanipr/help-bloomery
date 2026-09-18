<?php

namespace App\Filament\Helpdesk\Pages;

use App\Models\BranchEsbCode;
use App\Models\StockCardSetting;
use App\Services\EsbStockMovementService;
use App\Services\StockCardCategoryFilter;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;

class StockCardSettingsPage extends Page
{
    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Stock Card Settings';

    protected static ?string $title = 'Stock Card Settings';

    protected string $view = 'filament.helpdesk.pages.stock-card-settings-page';

    public bool $allCategories = true;

    public bool $showUncategorized = true;

    public array $selectedCategories = [];

    public string $categorySearch = '';

    #[Locked]
    public array $categoryOptions = [];

    #[Locked]
    public array $categorySources = [];

    #[Locked]
    public array $failedCompanies = [];

    #[Locked]
    public bool $categoriesLoaded = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view stock card settings') ?? false;
    }

    public function mount(): void
    {
        $setting = StockCardSetting::where('company_code', StockCardSetting::GLOBAL_COMPANY)->first();
        $this->allCategories = $setting?->all_categories ?? true;
        $this->showUncategorized = $setting?->show_uncategorized ?? true;
        $this->selectedCategories = $setting?->categories ?? [];
        $this->categorySources = $setting?->category_sources ?? [];
        $this->mergeCategoryOptions();
    }

    /** @return list<string> */
    public function companyCodes(): array
    {
        $configured = collect((array) config('esb.core.companies'))->filter(
            fn (array $credentials): bool => filled($credentials['username'] ?? null) && filled($credentials['password'] ?? null)
        )->keys();

        return BranchEsbCode::query()->where('is_active', true)->pluck('esb_comcode')
            ->merge($configured)->filter()->unique()->sort()->values()->all();
    }

    public function loadCategories(): void
    {
        $this->fetchCategories(false);
    }

    public function refreshCategories(): void
    {
        $this->fetchCategories(true);
    }

    private function fetchCategories(bool $refresh): void
    {
        abort_unless(static::canAccess(), 403);
        $this->failedCompanies = [];
        $companies = $this->companyCodes();
        $stored = StockCardSetting::where('company_code', StockCardSetting::GLOBAL_COMPANY)->first();
        $sources = array_intersect_key($stored?->category_sources ?? [], array_flip($companies));
        foreach ($companies as $company) {
            try {
                if ($refresh) {
                    Cache::forget('stock-movement.categories.'.$company);
                }
                $sources[$company] = collect(app(EsbStockMovementService::class)->categories($company))
                    ->map(fn (string $name): string => Str::squish($name))->filter()->unique()->values()->all();
            } catch (\Throwable $exception) {
                report($exception);
                $this->failedCompanies[] = $company;
            }
        }
        $this->categorySources = $sources;
        $this->mergeCategoryOptions();
        $this->categoriesLoaded = true;
        if (auth()->user()?->can('edit stock card settings')) {
            StockCardSetting::updateOrCreate(['company_code' => StockCardSetting::GLOBAL_COMPANY], ['category_sources' => $sources]);
        }
    }

    private function mergeCategoryOptions(): void
    {
        $normalizer = app(StockCardCategoryFilter::class);
        $this->categoryOptions = collect($this->categorySources)->flatten()
            ->map(fn (string $name): string => Str::squish($name))->filter()
            ->unique(fn (string $name): string => $normalizer->normalizeName($name))
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
    }

    /** @return list<string> */
    public function visibleCategories(): array
    {
        $normalizer = app(StockCardCategoryFilter::class);
        $search = $normalizer->normalizeName($this->categorySearch);

        return collect([...$this->selectedCategories, ...$this->categoryOptions])
            ->unique(fn (string $name): string => $normalizer->normalizeName($name))
            ->filter(fn (string $name): bool => $search === '' || str_contains($normalizer->normalizeName($name), $search))
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
    }

    public function save(): void
    {
        abort_unless(static::canAccess() && auth()->user()?->can('edit stock card settings'), 403);
        $storedCategories = StockCardSetting::where('company_code', StockCardSetting::GLOBAL_COMPANY)->first()?->categories ?? [];
        $this->validate([
            'allCategories' => ['boolean'],
            'showUncategorized' => ['boolean'],
            'selectedCategories' => ['array', Rule::requiredIf(! $this->allCategories), ...($this->allCategories ? [] : ['min:1'])],
            'selectedCategories.*' => ['string', 'max:255', 'distinct', Rule::in(array_unique([...$this->categoryOptions, ...$storedCategories]))],
        ]);
        $normalizer = app(StockCardCategoryFilter::class);
        StockCardSetting::updateOrCreate(['company_code' => StockCardSetting::GLOBAL_COMPANY], [
            'all_categories' => $this->allCategories,
            'categories' => $this->allCategories ? [] : collect($this->selectedCategories)
                ->unique(fn (string $name): string => $normalizer->normalizeName($name))->values()->all(),
            'show_uncategorized' => $this->showUncategorized,
        ]);
        Notification::make()->title('Stock Card settings berhasil disimpan')->success()->send();
    }
}
