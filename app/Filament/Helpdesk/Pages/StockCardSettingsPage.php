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
use Illuminate\Validation\ValidationException;
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

    /** @var array<string, array{category_name:string,mode:string,daily_count:int|null,rotate_daily:bool}> */
    public array $categoryRules = [];

    #[Locked]
    public array $categoryOptions = [];

    #[Locked]
    public array $categorySources = [];

    #[Locked]
    public array $failedCompanies = [];

    #[Locked]
    public bool $categoriesLoaded = false;

    #[Locked]
    public bool $categoriesRefreshing = false;

    /** @var list<string> */
    #[Locked]
    public array $refreshCompanyCodes = [];

    #[Locked]
    public int $refreshCompanyIndex = 0;

    #[Locked]
    public string $refreshCurrentCompany = '';

    /** @var list<array{company_code:string,branches:list<string>,status:string,category_count:int,message:?string}> */
    #[Locked]
    public array $categoryRefreshResults = [];

    /** @var array{successful_sources:int,failed_sources:int,raw_categories:int,merged_categories:int,duplicate_categories:int}|array{} */
    #[Locked]
    public array $categoryRefreshSummary = [];

    #[Locked]
    public ?string $categoriesRefreshedAt = null;

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
        $this->categoryRules = $setting?->category_rules ?? [];
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
        $this->startCategoryRefresh(false);
    }

    public function refreshCategories(): void
    {
        $this->startCategoryRefresh(true);
    }

    private function startCategoryRefresh(bool $refresh): void
    {
        abort_unless(static::canAccess(), 403);
        $this->failedCompanies = [];
        $companies = $this->companyCodes();
        if ($companies === []) {
            $this->categoriesLoaded = true;
            $this->categoryRefreshSummary = [
                'successful_sources' => 0,
                'failed_sources' => 0,
                'raw_categories' => 0,
                'merged_categories' => count($this->categoryOptions),
                'duplicate_categories' => 0,
            ];

            return;
        }

        $stored = StockCardSetting::where('company_code', StockCardSetting::GLOBAL_COMPANY)->first();
        $this->categorySources = array_intersect_key($stored?->category_sources ?? [], array_flip($companies));
        $this->categoriesRefreshing = true;
        $this->categoriesLoaded = false;
        $this->refreshCompanyCodes = $companies;
        $this->refreshCompanyIndex = 0;
        $this->refreshCurrentCompany = $companies[0];
        $this->categoryRefreshResults = [];
        $this->categoryRefreshSummary = [];
        $this->categoriesRefreshedAt = null;

        if ($refresh) {
            foreach ($companies as $company) {
                Cache::forget('stock-movement.categories.'.$company);
            }
        }

        $this->dispatch('stock-card-category-fetch-next');
    }

    public function fetchNextCategorySource(): void
    {
        if (! $this->categoriesRefreshing) {
            return;
        }

        $company = $this->refreshCompanyCodes[$this->refreshCompanyIndex] ?? null;
        if ($company === null) {
            $this->finishCategoryRefresh();

            return;
        }

        $this->refreshCurrentCompany = $company;
        $branches = BranchEsbCode::query()
            ->with('branch:id,name')
            ->where('esb_comcode', $company)
            ->where('is_active', true)
            ->get()
            ->pluck('branch.name')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        try {
            $categories = collect(app(EsbStockMovementService::class)->categories($company))
                ->map(fn (string $name): string => Str::squish($name))->filter()->unique()->values()->all();
            $this->categorySources[$company] = $categories;
            $this->categoryRefreshResults[] = [
                'company_code' => $company,
                'branches' => $branches,
                'status' => 'success',
                'category_count' => count($categories),
                'message' => null,
            ];
        } catch (\Throwable $exception) {
            report($exception);
            $this->failedCompanies[] = $company;
            $this->categoryRefreshResults[] = [
                'company_code' => $company,
                'branches' => $branches,
                'status' => 'failed',
                'category_count' => count($this->categorySources[$company] ?? []),
                'message' => 'Kategori terakhir tetap digunakan.',
            ];
        }

        $this->mergeCategoryOptions();
        $this->refreshCompanyIndex++;

        if ($this->refreshCompanyIndex < count($this->refreshCompanyCodes)) {
            $this->refreshCurrentCompany = $this->refreshCompanyCodes[$this->refreshCompanyIndex];
            $this->dispatch('stock-card-category-fetch-next');

            return;
        }

        $this->finishCategoryRefresh();
    }

    private function finishCategoryRefresh(): void
    {
        $this->mergeCategoryOptions();
        $rawCategories = collect($this->categorySources)->sum(fn (array $categories): int => count($categories));
        $successfulSources = collect($this->categoryRefreshResults)->where('status', 'success')->count();
        $this->categoryRefreshSummary = [
            'successful_sources' => $successfulSources,
            'failed_sources' => count($this->failedCompanies),
            'raw_categories' => $rawCategories,
            'merged_categories' => count($this->categoryOptions),
            'duplicate_categories' => max(0, $rawCategories - count($this->categoryOptions)),
        ];
        $this->categoriesRefreshing = false;
        $this->categoriesLoaded = true;
        $this->refreshCurrentCompany = '';
        $this->categoriesRefreshedAt = now()->format('d M Y H:i:s');

        if (auth()->user()?->can('edit stock card settings')) {
            StockCardSetting::updateOrCreate(
                ['company_code' => StockCardSetting::GLOBAL_COMPANY],
                ['category_sources' => $this->categorySources],
            );
        }
    }

    private function mergeCategoryOptions(): void
    {
        $normalizer = app(StockCardCategoryFilter::class);
        $this->categoryOptions = collect($this->categorySources)->flatten()
            ->map(fn (string $name): string => Str::squish($name))->filter()
            ->unique(fn (string $name): string => $normalizer->normalizeName($name))
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
        $this->ensureCategoryRules();
    }

    private function ensureCategoryRules(): void
    {
        $normalizer = app(StockCardCategoryFilter::class);
        foreach ($this->categoryOptions as $category) {
            $key = sha1($normalizer->normalizeName($category));
            $this->categoryRules[$key] ??= [
                'category_name' => $category,
                'mode' => 'all',
                'daily_count' => null,
                'rotate_daily' => false,
            ];
        }
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
            'categoryRules' => ['array'],
            'categoryRules.*.category_name' => ['required', 'string', 'max:255'],
            'categoryRules.*.mode' => ['required', Rule::in(['all', 'limited'])],
            'categoryRules.*.daily_count' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'categoryRules.*.rotate_daily' => ['boolean'],
        ]);
        $invalidCounts = collect($this->categoryRules)
            ->filter(fn (array $rule): bool => ($rule['mode'] ?? 'all') === 'limited' && blank($rule['daily_count'] ?? null));
        if ($invalidCounts->isNotEmpty()) {
            throw ValidationException::withMessages($invalidCounts->keys()->mapWithKeys(
                fn (string $key): array => ["categoryRules.{$key}.daily_count" => 'Jumlah produk per hari wajib diisi.']
            )->all());
        }
        $normalizer = app(StockCardCategoryFilter::class);
        StockCardSetting::updateOrCreate(['company_code' => StockCardSetting::GLOBAL_COMPANY], [
            'all_categories' => $this->allCategories,
            'categories' => $this->allCategories ? [] : collect($this->selectedCategories)
                ->unique(fn (string $name): string => $normalizer->normalizeName($name))->values()->all(),
            'show_uncategorized' => $this->showUncategorized,
            'category_rules' => collect($this->categoryRules)->map(function (array $rule): array {
                $limited = ($rule['mode'] ?? 'all') === 'limited';

                return [
                    'category_name' => Str::squish((string) $rule['category_name']),
                    'mode' => $limited ? 'limited' : 'all',
                    'daily_count' => $limited ? (int) ($rule['daily_count'] ?? 1) : null,
                    'rotate_daily' => $limited && (bool) ($rule['rotate_daily'] ?? false),
                ];
            })->values()->all(),
        ]);
        Notification::make()->title('Stock Card settings berhasil disimpan')->success()->send();
    }
}
