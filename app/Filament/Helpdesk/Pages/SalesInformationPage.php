<?php

namespace App\Filament\Helpdesk\Pages;

use App\Models\Branch;
use App\Models\BranchEsbCode;
use App\Models\Brand;
use App\Models\EsbPaymentMethodCache;
use App\Services\EsbService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use UnitEnum;

class SalesInformationPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|UnitEnum|null $navigationGroup = 'Analytics';

    protected string $view = 'filament.helpdesk.pages.sales-information-page';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view sales information') ?? false;
    }

    /** @var list<int> */
    public array $selectedBrandIds = [];

    /** @var list<int> */
    public array $selectedBranchIds = [];

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public string $comparisonType = 'none';

    public ?string $comparisonDateFrom = null;

    public ?string $comparisonDateTo = null;

    public function mount(): void
    {
        $this->dateFrom = today()->toDateString();
        $this->dateTo = today()->toDateString();
    }

    public bool $fetched = false;

    // Paged-fetch progress state — flattened across every active (branch,
    // ESB code pair) combination, so a branch with multiple ESB pairs is
    // fetched pair-by-pair just like separate branches used to be.
    public bool $isFetching = false;

    /** @var list<int> */
    public array $fetchPairIds = [];

    public int $fetchPairIndex = 0;

    public int $fetchCurrentPage = 0;

    public int $fetchTotalPages = 0;

    public ?string $fetchCacheKey = null;

    /** @var list<array{key: string, label: string, from: string, to: string}> */
    public array $fetchPeriods = [];

    public int $fetchPeriodIndex = 0;

    /** @var list<array{period: string, branch: string, code: string, page: int, message: string}> */
    public array $failedFetches = [];

    /** @var array<string, mixed> */
    public array $comparisonSummary = [];

    // ── KPI ───────────────────────────────────────────────────────────────
    public float $totalRevenue = 0;

    public int $totalTransactions = 0;

    public float $avgTransaction = 0;

    public int $totalItems = 0;

    public int $totalPax = 0;

    public float $avgPerPax = 0;

    public float $totalDiscount = 0;

    public float $discountMenuTotal = 0;

    public float $discountPromoTotal = 0;

    public float $discountVoucherTotal = 0;

    // ── Charts ────────────────────────────────────────────────────────────
    /** @var array{labels: list<string>, data: list<float>} */
    public array $chartRevenueTrend = ['labels' => [], 'data' => []];

    /** @var array{labels: list<string>, data: list<int>} */
    public array $chartTopMenus = ['labels' => [], 'data' => []];

    /** @var list<array{rank: int, name: string, quantity: int, percentage: float}> */
    public array $menuRanking = [];

    /** @var list<array{salesNumber: string, salesDate: string, branch: string, visitPurpose: string, pax: int, totalItems: int, grandTotal: float, discountTotal: float, paymentTotal: float, paymentMethods: string}> */
    public array $salesTransactions = [];

    /** @var list<array{salesNumber: string, salesDate: string, branch: string, menu: string, category: string, subCategory: string, quantity: int, total: float}> */
    public array $salesTransactionMenus = [];

    /** @var array{labels: list<string>, data: list<float>} */
    public array $chartPaymentMix = ['labels' => [], 'data' => []];

    /** @var array{labels: list<string>, data: list<int>} */
    public array $chartPeakHours = ['labels' => [], 'data' => []];

    /** @var array{labels: list<string>, data: list<float>} */
    public array $chartCategories = ['labels' => [], 'data' => []];

    /** @var array{labels: list<string>, data: list<float>} */
    public array $chartSubCategories = ['labels' => [], 'data' => []];

    /** @var array<string, array<string, array<string, array{qty: int, revenue: float}>>> */
    public array $categoryDetailMap = [];

    /** @var array{labels: list<string>, data: list<int>, revenue: list<float>} */
    public array $chartVisitPurpose = ['labels' => [], 'data' => [], 'revenue' => []];

    /** @var list<array{date: string, purpose: string, count: int, revenue: float}> */
    public array $visitPurposeByDate = [];

    // ── Tables ────────────────────────────────────────────────────────────
    /** @var list<array{name: string, type: string, total: float}> */
    public array $paymentTable = [];

    /** @var list<array{code: string, name: string, revenue: float, transactions: int, pax: int, avgPerTransaction: float, avgPerPax: float, discountTotal: float}> */
    public array $branchTable = [];

    /** @var list<array{name: string, count: int, discountTotal: float, revenue: float}> */
    public array $promoTable = [];

    public function getTitle(): string
    {
        return 'Sales Information';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    /** @return list<Brand> */
    public function getBrands(): array
    {
        return Brand::orderBy('name')->get()->all();
    }

    public function updatedSelectedBrandIds(): void
    {
        $this->selectedBranchIds = [];
    }

    public function toggleBrandId(int $id): void
    {
        if (in_array($id, $this->selectedBrandIds)) {
            $this->selectedBrandIds = array_values(array_filter($this->selectedBrandIds, fn ($v) => $v !== $id));
        } else {
            $this->selectedBrandIds[] = $id;
        }
        $this->updatedSelectedBrandIds();
    }

    public function toggleBranchId(int $id): void
    {
        if (in_array($id, $this->selectedBranchIds)) {
            $this->selectedBranchIds = array_values(array_filter($this->selectedBranchIds, fn ($v) => $v !== $id));
        } else {
            $this->selectedBranchIds[] = $id;
        }
    }

    /** @return list<Branch> */
    public function getBranches(): array
    {
        return Branch::with('esbCodes')
            ->whereHas('esbCodes', fn ($query) => $query->where('is_active', true))
            ->when($this->selectedBrandIds, fn ($q) => $q->whereIn('brand_id', $this->selectedBrandIds))
            ->orderBy('name')
            ->get()
            ->all();
    }

    /** @return array{0: string, 1: string} */
    private function getDateRange(): array
    {
        return [
            $this->dateFrom ?: '2020-01-01',
            $this->dateTo ?: now()->toDateString(),
        ];
    }

    public function updatedComparisonType(): void
    {
        $this->syncComparisonDates();
    }

    public function updatedDateFrom(): void
    {
        $this->syncComparisonDates();
    }

    public function updatedDateTo(): void
    {
        $this->syncComparisonDates();
    }

    private function syncComparisonDates(): void
    {
        if ($this->comparisonType === 'none' || $this->comparisonType === 'custom' || ! $this->dateFrom || ! $this->dateTo) {
            if ($this->comparisonType === 'none') {
                $this->comparisonDateFrom = null;
                $this->comparisonDateTo = null;
            }

            return;
        }

        $from = Carbon::parse($this->dateFrom);
        $to = Carbon::parse($this->dateTo);

        [$comparisonFrom, $comparisonTo] = match ($this->comparisonType) {
            'yoy' => [$from->copy()->subYearNoOverflow(), $to->copy()->subYearNoOverflow()],
            'previous_month' => [
                $from->copy()->subMonthNoOverflow()->startOfMonth(),
                $to->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            'previous_period' => (function () use ($from, $to): array {
                $days = $from->diffInDays($to) + 1;
                $comparisonTo = $from->copy()->subDay();

                return [$comparisonTo->copy()->subDays($days - 1), $comparisonTo];
            })(),
            default => [$from, $to],
        };

        $this->comparisonDateFrom = $comparisonFrom->toDateString();
        $this->comparisonDateTo = $comparisonTo->toDateString();
    }

    public function fetch(): void
    {
        $rules = [
            'dateFrom' => ['required', 'date'],
            'dateTo' => ['required', 'date', 'after_or_equal:dateFrom'],
        ];
        if ($this->comparisonType !== 'none') {
            $rules['comparisonDateFrom'] = ['required', 'date'];
            $rules['comparisonDateTo'] = ['required', 'date', 'after_or_equal:comparisonDateFrom'];
        }
        $this->validate($rules);

        if (! empty($this->selectedBranchIds)) {
            $branches = Branch::with('esbCodes')->whereIn('id', $this->selectedBranchIds)->get();
        } else {
            $branches = collect($this->getBranches());
        }

        $pairIds = $branches
            ->flatMap(fn (Branch $b) => $b->activeEsbCodes()->pluck('id'))
            ->values()
            ->all();

        if (empty($pairIds)) {
            Notification::make()->title('Tidak ada branch dengan kode ESB yang dikonfigurasi')->warning()->send();

            return;
        }

        $this->fetched = false;
        $this->isFetching = true;
        $this->fetchPairIds = $pairIds;
        $this->fetchPairIndex = 0;
        $this->fetchCurrentPage = 0;
        $this->fetchTotalPages = 0;
        $this->fetchPeriodIndex = 0;
        $this->failedFetches = [];
        $this->comparisonSummary = [];
        $this->fetchPeriods = [[
            'key' => 'primary',
            'label' => 'Periode Utama',
            'from' => (string) $this->dateFrom,
            'to' => (string) $this->dateTo,
        ]];
        if ($this->comparisonType !== 'none') {
            $this->fetchPeriods[] = [
                'key' => 'comparison',
                'label' => 'Periode Pembanding',
                'from' => (string) $this->comparisonDateFrom,
                'to' => (string) $this->comparisonDateTo,
            ];
        }
        $this->forgetFetchCache();
        $this->fetchCacheKey = (string) Str::uuid();
        $this->putFetchAccumulator('current', $this->newPeriodAcc());

        $this->dispatch('fetch-next-page');
    }

    private function newPeriodAcc(): array
    {
        return $this->initAcc();
    }

    public function fetchNextPage(): void
    {
        if (! $this->isFetching) {
            return;
        }

        $pairId = $this->fetchPairIds[$this->fetchPairIndex] ?? null;

        if (! $pairId) {
            $this->finishFetch();

            return;
        }

        $pair = BranchEsbCode::with('branch')->find($pairId);

        if (! $pair || ! $pair->esb_token) {
            $this->advanceBranch();

            return;
        }

        $period = $this->fetchPeriods[$this->fetchPeriodIndex] ?? null;
        if (! $period) {
            $this->finishFetch();

            return;
        }
        $dateFrom = $period['from'];
        $dateTo = $period['to'];

        try {
            if ($this->fetchCurrentPage === 0) {
                $this->putFetchAccumulator('pair-checkpoint', $this->getFetchAccumulator('current'));
            }

            ['data' => $rows, 'pageCount' => $pageCount] = (new EsbService)->getSalesPage(
                $pair->esb_branch_code, $dateFrom, $dateTo, $pair->esb_token, $this->fetchCurrentPage + 1
            );

            $this->fetchTotalPages = $pageCount;
            $this->fetchCurrentPage++;

            $acc = $this->getFetchAccumulator('current');
            foreach ($rows as $sale) {
                $this->accumulateSale($acc, $sale, $pair);
            }
            $this->putFetchAccumulator('current', $acc);

            if ($this->fetchCurrentPage < $this->fetchTotalPages) {
                $this->dispatch('fetch-next-page');

                return;
            }

            $this->advanceBranch();
        } catch (\RuntimeException $e) {
            $this->putFetchAccumulator(
                'current',
                $this->getFetchAccumulator('pair-checkpoint', $this->newPeriodAcc()),
            );
            $this->failedFetches[] = [
                'period' => $period['label'],
                'branch' => $pair->branch?->name ?? $pair->esb_branch_code,
                'code' => $pair->esb_branch_code,
                'page' => $this->fetchCurrentPage + 1,
                'message' => $e->getMessage(),
            ];

            Notification::make()
                ->title('Satu branch dilewati')
                ->body(($pair->branch?->name ?? $pair->esb_branch_code).' gagal dihubungi. Pengambilan branch lain tetap dilanjutkan.')
                ->warning()
                ->send();

            $this->advanceBranch();
        }
    }

    private function advanceBranch(): void
    {
        $this->fetchPairIndex++;
        $this->fetchCurrentPage = 0;
        $this->fetchTotalPages = 0;

        if ($this->fetchPairIndex < count($this->fetchPairIds)) {
            $this->dispatch('fetch-next-page');
        } else {
            $this->finishPeriodFetch();
        }
    }

    private function finishPeriodFetch(): void
    {
        if ($this->fetchPeriodIndex === 0) {
            $this->putFetchAccumulator('primary', $this->getFetchAccumulator('current'));
        }

        if ($this->fetchPeriodIndex + 1 < count($this->fetchPeriods)) {
            $this->fetchPeriodIndex++;
            $this->fetchPairIndex = 0;
            $this->fetchCurrentPage = 0;
            $this->fetchTotalPages = 0;
            $this->putFetchAccumulator('current', $this->newPeriodAcc());
            $this->dispatch('fetch-next-page');

            return;
        }

        $this->finishFetch();
    }

    private function finishFetch(): void
    {
        $current = $this->getFetchAccumulator('current');
        $primary = $this->getFetchAccumulator('primary', $current);
        if (! ($primary['hasData'] ?? false)) {
            Notification::make()->title('Tidak ada data penjualan pada periode ini')->info()->send();
            $this->stopFetch();

            return;
        }

        $this->finalizeData($primary);
        if (count($this->fetchPeriods) > 1) {
            $this->comparisonSummary = $this->buildComparisonSummary($primary, $current);
        }

        $salesTransactions = $this->salesTransactions;
        $salesTransactionMenus = $this->salesTransactionMenus;
        $this->salesTransactions = [];
        $this->salesTransactionMenus = [];

        $this->stopFetch();
        $this->fetched = true;

        $this->dispatch('sales-loaded',
            revenueTrend: $this->chartRevenueTrend,
            topMenus: $this->chartTopMenus,
            menuRanking: $this->menuRanking,
            salesTransactions: $salesTransactions,
            salesTransactionMenus: $salesTransactionMenus,
            paymentMix: $this->chartPaymentMix,
            peakHours: $this->chartPeakHours,
            categories: $this->chartCategories,
            subCategories: $this->chartSubCategories,
            visitPurpose: $this->chartVisitPurpose,
            visitPurposeByDate: $this->visitPurposeByDate,
            branchTable: $this->branchTable,
            categoryDetailMap: $this->categoryDetailMap,
            promoTable: $this->promoTable,
            paymentTable: $this->paymentTable,
            comparison: $this->comparisonSummary,
            failedFetches: $this->failedFetches,
            dateFrom: $this->dateFrom,
            dateTo: $this->dateTo,
            kpi: [
                'totalRevenue' => $this->totalRevenue,
                'totalTransactions' => $this->totalTransactions,
                'avgTransaction' => $this->avgTransaction,
                'totalPax' => $this->totalPax,
                'avgPerPax' => $this->avgPerPax,
                'totalItems' => $this->totalItems,
                'totalDiscount' => $this->totalDiscount,
                'discountMenuTotal' => $this->discountMenuTotal,
                'discountPromoTotal' => $this->discountPromoTotal,
                'discountVoucherTotal' => $this->discountVoucherTotal,
            ],
        );
    }

    private function stopFetch(): void
    {
        $this->isFetching = false;
        $this->forgetFetchCache();
        $this->fetchCacheKey = null;
    }

    /** @return array<string, mixed> */
    private function getFetchAccumulator(string $name, ?array $default = null): array
    {
        if (! $this->fetchCacheKey) {
            return $default ?? $this->newPeriodAcc();
        }

        return Cache::get($this->fetchAccumulatorCacheKey($name), $default ?? $this->newPeriodAcc());
    }

    /** @param array<string, mixed> $accumulator */
    private function putFetchAccumulator(string $name, array $accumulator): void
    {
        if (! $this->fetchCacheKey) {
            return;
        }

        Cache::put($this->fetchAccumulatorCacheKey($name), $accumulator, now()->addHour());
    }

    private function forgetFetchCache(): void
    {
        if (! $this->fetchCacheKey) {
            return;
        }

        Cache::forget($this->fetchAccumulatorCacheKey('current'));
        Cache::forget($this->fetchAccumulatorCacheKey('primary'));
        Cache::forget($this->fetchAccumulatorCacheKey('pair-checkpoint'));
    }

    private function fetchAccumulatorCacheKey(string $name): string
    {
        return 'sales-information:'.auth()->id().':'.$this->fetchCacheKey.':'.$name;
    }

    /** @return array<string, mixed> */
    private function initAcc(): array
    {
        return [
            'hasData' => false,
            'revenue' => 0.0,
            'transactions' => 0,
            'items' => 0,
            'paxTotal' => 0,
            'discountMenuTotal' => 0.0,
            'discountPromoTotal' => 0.0,
            'discountVoucherTotal' => 0.0,
            'byDate' => [],
            'menuQty' => [],
            'paymentRows' => [],
            'hourly' => array_fill(0, 24, 0),
            'categoryRevenue' => [],
            'subCategoryRevenue' => [],
            'visitPurpose' => [],
            'visitPurposeRevenue' => [],
            'visitPurposeByDate' => [],
            'branches' => [],
            'promos' => [],
            'categoryDetailMap' => [],
            'salesTransactions' => [],
            'salesTransactionMenus' => [],
        ];
    }

    /** @param array<string, mixed> $acc */
    private function accumulateSale(array &$acc, array $sale, BranchEsbCode $pair): void
    {
        $acc['hasData'] = true;
        $grandTotal = (float) ($sale['grandTotal'] ?? 0);
        $pax = (int) ($sale['paxTotal'] ?? 0);

        $discountMenu = (float) ($sale['menuDiscountTotal'] ?? 0);
        $discountVoucher = (float) ($sale['voucherDiscountTotal'] ?? 0);
        // promotionDiscount is a configured rate (e.g. 13 for 13%), not the monetary amount.
        // Derive the actual promo discount as the remainder of discountTotal.
        $discountPromo = max(0.0, (float) ($sale['discountTotal'] ?? 0) - $discountMenu - $discountVoucher);

        // Revenue = sum of payment amounts so it always matches Rekapitulasi Pembayaran.
        // Payment amounts are net of CASH change given back (see EsbService::netPaymentAmount).
        $esbService = app(EsbService::class);
        $paymentTotal = (float) array_sum(array_map(
            fn (array $payment): float => $esbService->netPaymentAmount($sale, $payment),
            $sale['salesPayments'] ?? [],
        ));
        $netRevenue = $paymentTotal > 0 ? $paymentTotal : $grandTotal;

        $salesNumber = (string) ($sale['salesNum'] ?? '');
        $salesDate = (string) ($sale['salesDateOut'] ?? $sale['salesDateIn'] ?? $sale['salesDate'] ?? '');
        $sourceBranchCode = (string) ($sale['branchCode'] ?? $pair->esb_branch_code);
        $branchName = (string) ($pair->branch?->name ?? $sale['branchName'] ?? $sourceBranchCode);
        $transactionItemCount = (int) array_sum(array_map(
            fn (array $menu): int => (int) ($menu['qty'] ?? 0),
            $sale['salesMenus'] ?? [],
        ));
        $paymentMethods = collect($sale['salesPayments'] ?? [])
            ->pluck('paymentMethodName')
            ->filter()
            ->unique()
            ->implode(', ');

        $acc['salesTransactions'][] = [
            'salesNumber' => $salesNumber,
            'salesDate' => $salesDate,
            'branch' => $branchName,
            'visitPurpose' => (string) ($sale['visitPurposeName'] ?: 'Lainnya'),
            'pax' => $pax,
            'totalItems' => $transactionItemCount,
            'grandTotal' => $grandTotal,
            'discountTotal' => (float) ($sale['discountTotal'] ?? 0),
            'paymentTotal' => $netRevenue,
            'paymentMethods' => $paymentMethods,
        ];

        $acc['revenue'] += $netRevenue;
        $acc['transactions']++;
        $acc['paxTotal'] += $pax;
        $acc['discountMenuTotal'] += $discountMenu;
        $acc['discountPromoTotal'] += $discountPromo;
        $acc['discountVoucherTotal'] += $discountVoucher;

        $date = $sale['salesDate'] ?? '';
        $acc['byDate'][$date] = ($acc['byDate'][$date] ?? 0.0) + $netRevenue;

        if ($dateIn = $sale['salesDateIn'] ?? null) {
            $acc['hourly'][(int) date('G', strtotime($dateIn))]++;
        }

        $purpose = $sale['visitPurposeName'] ?: 'Lainnya';
        $acc['visitPurpose'][$purpose] = ($acc['visitPurpose'][$purpose] ?? 0) + 1;
        $acc['visitPurposeRevenue'][$purpose] = ($acc['visitPurposeRevenue'][$purpose] ?? 0.0) + $netRevenue;

        if (! isset($acc['visitPurposeByDate'][$date][$purpose])) {
            $acc['visitPurposeByDate'][$date][$purpose] = ['count' => 0, 'revenue' => 0.0];
        }
        $acc['visitPurposeByDate'][$date][$purpose]['count']++;
        $acc['visitPurposeByDate'][$date][$purpose]['revenue'] += $netRevenue;

        // Per-branch accumulation
        $branchKey = 'branch:'.$pair->branch_id;
        if (! isset($acc['branches'][$branchKey])) {
            $acc['branches'][$branchKey] = [
                'codes' => [],
                'name' => $branchName,
                'revenue' => 0.0,
                'transactions' => 0,
                'pax' => 0,
                'discountTotal' => 0.0,
            ];
        }
        $acc['branches'][$branchKey]['codes'][$sourceBranchCode] = true;
        $acc['branches'][$branchKey]['revenue'] += $netRevenue;
        $acc['branches'][$branchKey]['transactions']++;
        $acc['branches'][$branchKey]['pax'] += $pax;
        $acc['branches'][$branchKey]['discountTotal'] += (float) ($sale['discountTotal'] ?? 0);

        // Promo accumulation
        $promoName = $sale['promotionName'] ?? null;
        if ($promoName) {
            if (! isset($acc['promos'][$promoName])) {
                $acc['promos'][$promoName] = ['name' => $promoName, 'count' => 0, 'discountTotal' => 0.0, 'revenue' => 0.0];
            }
            $acc['promos'][$promoName]['count']++;
            $acc['promos'][$promoName]['discountTotal'] += $discountPromo;
            $acc['promos'][$promoName]['revenue'] += $netRevenue;
        }

        foreach ($sale['salesMenus'] ?? [] as $menu) {
            $qty = (int) ($menu['qty'] ?? 0);
            $acc['items'] += $qty;

            $menuName = $menu['menuName'] ?? 'Unknown';
            $acc['menuQty'][$menuName] = ($acc['menuQty'][$menuName] ?? 0) + $qty;

            $cat = $menu['menuCategoryName'] ?: 'Lainnya';
            $acc['categoryRevenue'][$cat] = ($acc['categoryRevenue'][$cat] ?? 0.0) + (float) ($menu['total'] ?? 0);

            $subCat = $menu['menuCategoryDetailName'] ?: 'Lainnya';
            $acc['subCategoryRevenue'][$subCat] = ($acc['subCategoryRevenue'][$subCat] ?? 0.0) + (float) ($menu['total'] ?? 0);

            if (! isset($acc['categoryDetailMap'][$cat][$subCat][$menuName])) {
                $acc['categoryDetailMap'][$cat][$subCat][$menuName] = ['qty' => 0, 'revenue' => 0.0];
            }
            $acc['categoryDetailMap'][$cat][$subCat][$menuName]['qty'] += $qty;
            $acc['categoryDetailMap'][$cat][$subCat][$menuName]['revenue'] += (float) ($menu['total'] ?? 0);

            $acc['salesTransactionMenus'][] = [
                'salesNumber' => $salesNumber,
                'salesDate' => $salesDate,
                'branch' => $branchName,
                'menu' => $menuName,
                'category' => $cat,
                'subCategory' => $subCat,
                'quantity' => $qty,
                'total' => (float) ($menu['total'] ?? 0),
            ];
        }

        foreach ($sale['salesPayments'] ?? [] as $payment) {
            $rawName = $payment['paymentMethodName'] ?? 'Unknown';
            $type = $payment['paymentMethodTypeName'] ?? '';
            $amount = $esbService->netPaymentAmount($sale, $payment);
            $esbId = (int) ($payment['paymentMethodID'] ?? 0);

            $method = $rawName;

            // Populate cache with discovered payment methods
            if ($esbId) {
                EsbPaymentMethodCache::upsertFromEsb($payment, $sourceBranchCode, $branchName);
            }

            if (! isset($acc['paymentRows'][$method])) {
                $acc['paymentRows'][$method] = ['name' => $method, 'type' => $type, 'total' => 0.0];
            }
            $acc['paymentRows'][$method]['total'] += $amount;
        }
    }

    /** @param array<string, mixed> $primary @param array<string, mixed> $comparison */
    private function buildComparisonSummary(array $primary, array $comparison): array
    {
        $metrics = [
            'totalRevenue' => [(float) $primary['revenue'], (float) ($comparison['revenue'] ?? 0)],
            'totalTransactions' => [(int) $primary['transactions'], (int) ($comparison['transactions'] ?? 0)],
            'avgTransaction' => [
                $primary['transactions'] > 0 ? $primary['revenue'] / $primary['transactions'] : 0,
                ($comparison['transactions'] ?? 0) > 0 ? $comparison['revenue'] / $comparison['transactions'] : 0,
            ],
            'totalPax' => [(int) $primary['paxTotal'], (int) ($comparison['paxTotal'] ?? 0)],
            'avgPerPax' => [
                $primary['paxTotal'] > 0 ? $primary['revenue'] / $primary['paxTotal'] : 0,
                ($comparison['paxTotal'] ?? 0) > 0 ? $comparison['revenue'] / $comparison['paxTotal'] : 0,
            ],
            'totalItems' => [(int) $primary['items'], (int) ($comparison['items'] ?? 0)],
            'totalDiscount' => [
                (float) ($primary['discountMenuTotal'] + $primary['discountPromoTotal'] + $primary['discountVoucherTotal']),
                (float) (($comparison['discountMenuTotal'] ?? 0) + ($comparison['discountPromoTotal'] ?? 0) + ($comparison['discountVoucherTotal'] ?? 0)),
            ],
        ];

        $kpis = [];
        foreach ($metrics as $key => [$current, $previous]) {
            $kpis[$key] = [
                'current' => $current,
                'comparison' => $previous,
                'change' => $previous != 0
                    ? (($current - $previous) / abs($previous)) * 100
                    : ($current == 0 ? 0 : null),
            ];
        }

        $primaryTrend = $primary['byDate'] ?? [];
        $comparisonTrend = $comparison['byDate'] ?? [];
        ksort($primaryTrend);
        ksort($comparisonTrend);
        $trendLength = max(count($primaryTrend), count($comparisonTrend));

        $primaryBranches = $primary['branches'] ?? [];
        $comparisonBranches = $comparison['branches'] ?? [];
        $branchKeys = collect(array_keys($primaryBranches))
            ->merge(array_keys($comparisonBranches))
            ->unique();
        $branchRows = $branchKeys->map(function (string $branchKey) use ($primaryBranches, $comparisonBranches): array {
            $current = $primaryBranches[$branchKey] ?? [];
            $previous = $comparisonBranches[$branchKey] ?? [];
            $currentRevenue = (float) ($current['revenue'] ?? 0);
            $previousRevenue = (float) ($previous['revenue'] ?? 0);
            $codes = array_unique(array_merge(
                array_keys($current['codes'] ?? []),
                array_keys($previous['codes'] ?? []),
            ));

            return [
                'code' => implode(', ', $codes),
                'name' => $current['name'] ?? $previous['name'] ?? $branchKey,
                'currentRevenue' => $currentRevenue,
                'comparisonRevenue' => $previousRevenue,
                'revenueChange' => $previousRevenue != 0
                    ? (($currentRevenue - $previousRevenue) / abs($previousRevenue)) * 100
                    : ($currentRevenue == 0 ? 0 : null),
                'currentTransactions' => (int) ($current['transactions'] ?? 0),
                'comparisonTransactions' => (int) ($previous['transactions'] ?? 0),
            ];
        })->sortByDesc(fn (array $row): float => (float) ($row['revenueChange'] ?? -INF))->values()->all();

        $primaryPeriod = $this->fetchPeriods[0];
        $comparisonPeriod = $this->fetchPeriods[1];

        return [
            'enabled' => true,
            'type' => $this->comparisonType,
            'primaryLabel' => Carbon::parse($primaryPeriod['from'])->isoFormat('D MMM Y').' – '.Carbon::parse($primaryPeriod['to'])->isoFormat('D MMM Y'),
            'comparisonLabel' => Carbon::parse($comparisonPeriod['from'])->isoFormat('D MMM Y').' – '.Carbon::parse($comparisonPeriod['to'])->isoFormat('D MMM Y'),
            'kpis' => $kpis,
            'revenueTrend' => [
                'labels' => array_map(fn (int $index): string => 'Hari '.($index + 1), range(0, max(0, $trendLength - 1))),
                'primary' => array_pad(array_values($primaryTrend), $trendLength, null),
                'comparison' => array_pad(array_values($comparisonTrend), $trendLength, null),
            ],
            'branches' => $branchRows,
        ];
    }

    /** @param array<string, mixed> $acc */
    private function finalizeData(array $acc): void
    {
        // KPIs
        $this->totalRevenue = $acc['revenue'];
        $this->totalTransactions = $acc['transactions'];
        $this->avgTransaction = $acc['transactions'] > 0 ? $acc['revenue'] / $acc['transactions'] : 0;
        $this->totalItems = $acc['items'];
        $this->totalPax = $acc['paxTotal'];
        $this->avgPerPax = $acc['paxTotal'] > 0 ? $acc['revenue'] / $acc['paxTotal'] : 0;
        $this->discountMenuTotal = $acc['discountMenuTotal'];
        $this->discountPromoTotal = $acc['discountPromoTotal'];
        $this->discountVoucherTotal = $acc['discountVoucherTotal'];
        $this->totalDiscount = $this->discountMenuTotal + $this->discountPromoTotal + $this->discountVoucherTotal;

        // Sort
        ksort($acc['byDate']);
        arsort($acc['menuQty']);
        uasort($acc['paymentRows'], fn ($a, $b) => $b['total'] <=> $a['total']);
        arsort($acc['categoryRevenue']);
        arsort($acc['subCategoryRevenue']);
        arsort($acc['visitPurpose']);
        uasort($acc['branches'], fn ($a, $b) => $b['revenue'] <=> $a['revenue']);
        uasort($acc['promos'], fn ($a, $b) => $b['count'] <=> $a['count']);

        // Payment table
        $this->paymentTable = array_values($acc['paymentRows']);

        // Branch table
        $this->branchTable = array_values(array_map(fn ($b) => [
            'code' => implode(', ', array_keys($b['codes'])),
            'name' => $b['name'],
            'revenue' => $b['revenue'],
            'transactions' => $b['transactions'],
            'pax' => $b['pax'],
            'avgPerTransaction' => $b['transactions'] > 0 ? $b['revenue'] / $b['transactions'] : 0.0,
            'avgPerPax' => $b['pax'] > 0 ? $b['revenue'] / $b['pax'] : 0.0,
            'discountTotal' => $b['discountTotal'],
        ], $acc['branches']));

        // Promo table
        $this->promoTable = array_values($acc['promos']);

        // Charts
        $top10 = array_slice($acc['menuQty'], 0, 10, true);
        $totalMenuQuantity = array_sum($acc['menuQty']);
        $this->menuRanking = [];
        foreach ($acc['menuQty'] as $menuName => $quantity) {
            $this->menuRanking[] = [
                'rank' => count($this->menuRanking) + 1,
                'name' => $menuName,
                'quantity' => $quantity,
                'percentage' => $totalMenuQuantity > 0 ? ($quantity / $totalMenuQuantity) * 100 : 0.0,
            ];
        }
        $this->salesTransactions = $acc['salesTransactions'];
        $this->salesTransactionMenus = $acc['salesTransactionMenus'];
        $top15SubCats = array_slice($acc['subCategoryRevenue'], 0, 15, true);

        ksort($acc['categoryDetailMap']);
        foreach ($acc['categoryDetailMap'] as &$subCats) {
            ksort($subCats);
            foreach ($subCats as &$menus) {
                uasort($menus, fn ($a, $b) => $b['qty'] <=> $a['qty']);
            }
        }
        unset($subCats, $menus);
        $this->categoryDetailMap = $acc['categoryDetailMap'];

        $this->chartRevenueTrend = ['labels' => array_keys($acc['byDate']), 'data' => array_values($acc['byDate'])];
        $this->chartTopMenus = ['labels' => array_keys($top10), 'data' => array_values($top10)];
        $this->chartPaymentMix = [
            'labels' => array_column($this->paymentTable, 'name'),
            'data' => array_column($this->paymentTable, 'total'),
        ];
        $this->chartPeakHours = [
            'labels' => array_map(fn ($h) => sprintf('%02d:00', $h), range(0, 23)),
            'data' => array_values($acc['hourly']),
        ];
        $this->chartCategories = ['labels' => array_keys($acc['categoryRevenue']), 'data' => array_values($acc['categoryRevenue'])];
        $this->chartSubCategories = ['labels' => array_keys($top15SubCats), 'data' => array_values($top15SubCats)];
        $this->chartVisitPurpose = [
            'labels' => array_keys($acc['visitPurpose']),
            'data' => array_values($acc['visitPurpose']),
            'revenue' => array_map(
                fn (string $purpose): float => $acc['visitPurposeRevenue'][$purpose] ?? 0.0,
                array_keys($acc['visitPurpose']),
            ),
        ];

        // Per-date breakdown, sorted chronologically then by purpose label.
        ksort($acc['visitPurposeByDate']);
        $rows = [];
        foreach ($acc['visitPurposeByDate'] as $date => $purposes) {
            ksort($purposes);
            foreach ($purposes as $purpose => $stats) {
                $rows[] = [
                    'date' => $date,
                    'purpose' => $purpose,
                    'count' => $stats['count'],
                    'revenue' => $stats['revenue'],
                ];
            }
        }
        $this->visitPurposeByDate = $rows;
    }
}
