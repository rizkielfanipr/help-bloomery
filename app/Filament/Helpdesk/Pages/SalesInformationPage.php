<?php

namespace App\Filament\Helpdesk\Pages;

use App\Models\Branch;
use App\Services\EsbService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

class SalesInformationPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|UnitEnum|null $navigationGroup = 'Analytics';

    protected string $view = 'filament.helpdesk.pages.sales-information-page';

    public ?int $selectedBranchId = null;

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public bool $fetched = false;

    // Paged-fetch progress state
    public bool $isFetching = false;

    /** @var list<int> */
    public array $fetchBranchIds = [];

    public int $fetchBranchIndex = 0;

    public int $fetchCurrentPage = 0;

    public int $fetchTotalPages = 0;

    /** @var array<string, mixed> */
    public array $fetchAcc = [];

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

    /** @var array{labels: list<string>, data: list<int>} */
    public array $chartVisitPurpose = ['labels' => [], 'data' => []];

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

    /** @return list<Branch> */
    public function getBranches(): array
    {
        return Branch::whereNotNull('esb_branch_code')
            ->whereNotNull('esb_comcode')
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

    public function fetch(): void
    {
        if ($this->selectedBranchId) {
            $this->validate(['selectedBranchId' => ['integer', 'exists:branches,id']]);

            $branch = Branch::find($this->selectedBranchId);

            if (! $branch?->esb_branch_code) {
                Notification::make()->title('Branch belum memiliki ESB Branch Code')->warning()->send();

                return;
            }

            if (! $branch->esb_token) {
                Notification::make()->title('Token ESB untuk branch ini belum dikonfigurasi')->warning()->send();

                return;
            }

            $branchIds = [$branch->id];
        } else {
            $branchIds = collect($this->getBranches())
                ->filter(fn (Branch $b) => $b->esb_token !== '')
                ->pluck('id')
                ->values()
                ->all();

            if (empty($branchIds)) {
                Notification::make()->title('Tidak ada branch dengan token ESB yang dikonfigurasi')->warning()->send();

                return;
            }
        }

        $this->fetched = false;
        $this->isFetching = true;
        $this->fetchBranchIds = $branchIds;
        $this->fetchBranchIndex = 0;
        $this->fetchCurrentPage = 0;
        $this->fetchTotalPages = 0;
        $this->fetchAcc = $this->initAcc();

        $this->fetchNextPage();
    }

    public function fetchNextPage(): void
    {
        if (! $this->isFetching) {
            return;
        }

        $branchId = $this->fetchBranchIds[$this->fetchBranchIndex] ?? null;

        if (! $branchId) {
            $this->finishFetch();

            return;
        }

        $branch = Branch::find($branchId);

        if (! $branch || ! $branch->esb_token) {
            $this->advanceBranch();

            return;
        }

        [$dateFrom, $dateTo] = $this->getDateRange();

        try {
            ['data' => $rows, 'pageCount' => $pageCount] = (new EsbService)->getSalesPage(
                $branch->esb_branch_code, $dateFrom, $dateTo, $branch->esb_token, $this->fetchCurrentPage + 1
            );

            $this->fetchTotalPages = $pageCount;
            $this->fetchCurrentPage++;

            $acc = $this->fetchAcc;
            foreach ($rows as $sale) {
                $this->accumulateSale($acc, $sale);
            }
            $this->fetchAcc = $acc;

            if ($this->fetchCurrentPage < $this->fetchTotalPages) {
                $this->dispatch('fetch-next-page');

                return;
            }

            $this->advanceBranch();
        } catch (\RuntimeException $e) {
            $this->stopFetch();
            Notification::make()
                ->title('Gagal mengambil data ESB')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function advanceBranch(): void
    {
        $this->fetchBranchIndex++;
        $this->fetchCurrentPage = 0;
        $this->fetchTotalPages = 0;

        if ($this->fetchBranchIndex < count($this->fetchBranchIds)) {
            $this->dispatch('fetch-next-page');
        } else {
            $this->finishFetch();
        }
    }

    private function finishFetch(): void
    {
        if (! $this->fetchAcc['hasData']) {
            Notification::make()->title('Tidak ada data penjualan pada periode ini')->info()->send();
            $this->stopFetch();

            return;
        }

        $this->finalizeData($this->fetchAcc);
        $this->stopFetch();
        $this->fetched = true;

        $this->dispatch('sales-loaded',
            revenueTrend: $this->chartRevenueTrend,
            topMenus: $this->chartTopMenus,
            paymentMix: $this->chartPaymentMix,
            peakHours: $this->chartPeakHours,
            categories: $this->chartCategories,
            subCategories: $this->chartSubCategories,
            visitPurpose: $this->chartVisitPurpose,
            branchTable: $this->branchTable,
            categoryDetailMap: $this->categoryDetailMap,
        );
    }

    private function stopFetch(): void
    {
        $this->isFetching = false;
        $this->fetchAcc = [];
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
            'branches' => [],
            'promos' => [],
            'categoryDetailMap' => [],
        ];
    }

    /** @param array<string, mixed> $acc */
    private function accumulateSale(array &$acc, array $sale): void
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
        $paymentTotal = (float) array_sum(array_column($sale['salesPayments'] ?? [], 'paymentAmount'));
        $netRevenue = $paymentTotal > 0 ? $paymentTotal : $grandTotal;

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

        // Per-branch accumulation
        $branchCode = $sale['branchCode'] ?? 'Unknown';
        $branchName = $sale['branchName'] ?? $branchCode;
        if (! isset($acc['branches'][$branchCode])) {
            $acc['branches'][$branchCode] = [
                'code' => $branchCode,
                'name' => $branchName,
                'revenue' => 0.0,
                'transactions' => 0,
                'pax' => 0,
                'discountTotal' => 0.0,
            ];
        }
        $acc['branches'][$branchCode]['revenue'] += $netRevenue;
        $acc['branches'][$branchCode]['transactions']++;
        $acc['branches'][$branchCode]['pax'] += $pax;
        $acc['branches'][$branchCode]['discountTotal'] += (float) ($sale['discountTotal'] ?? 0);

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
        }

        foreach ($sale['salesPayments'] ?? [] as $payment) {
            $method = $payment['paymentMethodName'] ?? 'Unknown';
            $type = $payment['paymentMethodTypeName'] ?? '';
            $amount = (float) ($payment['paymentAmount'] ?? 0);
            if (! isset($acc['paymentRows'][$method])) {
                $acc['paymentRows'][$method] = ['name' => $method, 'type' => $type, 'total' => 0.0];
            }
            $acc['paymentRows'][$method]['total'] += $amount;
        }
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
            'code' => $b['code'],
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
        $this->chartVisitPurpose = ['labels' => array_keys($acc['visitPurpose']), 'data' => array_values($acc['visitPurpose'])];
    }
}
