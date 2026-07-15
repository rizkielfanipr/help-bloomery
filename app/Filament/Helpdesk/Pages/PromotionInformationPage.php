<?php

namespace App\Filament\Helpdesk\Pages;

use App\Models\Branch;
use App\Models\Brand;
use App\Services\EsbService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

class PromotionInformationPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|UnitEnum|null $navigationGroup = 'Analytics';

    protected string $view = 'filament.helpdesk.pages.promotion-information-page';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view promotion information') ?? false;
    }

    /** @var list<int> */
    public array $selectedBrandIds = [];

    /** @var list<int> */
    public array $selectedBranchIds = [];

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public function mount(): void
    {
        $this->dateFrom = today()->toDateString();
    }

    public bool $fetched = false;

    public string $selectedPromoType = '';

    // Paged-fetch state
    public bool $isFetching = false;

    /** @var list<int> */
    public array $fetchBranchIds = [];

    public int $fetchBranchIndex = 0;

    public int $fetchCurrentPage = 0;

    public int $fetchTotalPages = 0;

    /** @var array<string, mixed> */
    public array $fetchAcc = [];

    // Promotion catalog from ESB promo API (keyed by promotionID)
    /** @var array<int, mixed> */
    public array $promotionCatalog = [];

    // ── KPIs ──────────────────────────────────────────────────────────────
    public int $totalTransactions = 0;

    public int $promoTransactions = 0;

    public float $promoAdoptionRate = 0.0;

    public float $totalRevenue = 0.0;

    public float $promoRevenue = 0.0;

    public float $totalDiscount = 0.0;

    public float $avgPromoTransaction = 0.0;

    public float $avgNonPromoTransaction = 0.0;

    // ── Charts ────────────────────────────────────────────────────────────
    /** @var array{labels: list<string>, data: list<int>} */
    public array $chartPromoUsage = ['labels' => [], 'data' => []];

    /** @var array{labels: list<string>, data: list<float>} */
    public array $chartPromoDiscount = ['labels' => [], 'data' => []];

    /** @var array{labels: list<string>, data: list<int>} */
    public array $chartPromoTrend = ['labels' => [], 'data' => []];

    // ── Tables ────────────────────────────────────────────────────────────
    /** @var list<array{id: int, name: string, code: string, type: string, configDiscount: float, startDate: ?string, endDate: ?string, count: int, totalDiscount: float, totalRevenue: float, avgTransaction: float, adoptionRate: float, isActive: bool}> */
    public array $promoPerformanceTable = [];

    /** @var array<string, array<string, int>> */
    public array $branchPromoTable = [];

    public function getTitle(): string
    {
        return 'Promotion Information';
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
        return Branch::whereNotNull('esb_branch_code')
            ->whereNotNull('esb_comcode')
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

    public function fetch(): void
    {
        $this->validate(['dateTo' => ['required', 'date']]);

        if (! empty($this->selectedBranchIds)) {
            $branchIds = Branch::whereIn('id', $this->selectedBranchIds)
                ->whereNotNull('esb_branch_code')
                ->get()
                ->filter(fn (Branch $b) => ! empty($b->esb_token))
                ->pluck('id')
                ->values()
                ->all();
        } else {
            $branchIds = collect($this->getBranches())
                ->filter(fn (Branch $b) => ! empty($b->esb_token))
                ->pluck('id')
                ->values()
                ->all();
        }

        if (empty($branchIds)) {
            Notification::make()->title('Tidak ada branch dengan token ESB yang dikonfigurasi')->warning()->send();

            return;
        }

        // Fetch promotion catalog (fast, single call per branch)
        $this->promotionCatalog = [];
        $esb = new EsbService;
        foreach ($branchIds as $branchId) {
            $b = Branch::find($branchId);
            if (! $b?->esb_branch_code) {
                continue;
            }
            try {
                $promos = $esb->getPromotionList($b->esb_branch_code, $b->esb_token);
                foreach ($promos as $promo) {
                    $id = (int) ($promo['promotionID'] ?? 0);
                    if ($id && ! isset($this->promotionCatalog[$id])) {
                        $this->promotionCatalog[$id] = $promo;
                    }
                }
            } catch (\RuntimeException) {
                // catalog fetch failure is non-fatal; sales fetch continues
            }
        }

        $this->fetched = false;
        $this->selectedPromoType = '';
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

        $this->dispatch('promo-loaded',
            promoUsage: $this->chartPromoUsage,
            promoDiscount: $this->chartPromoDiscount,
            promoTrend: $this->chartPromoTrend,
            promoPerformanceTable: $this->promoPerformanceTable,
            branchPromoTable: $this->branchPromoTable,
            promotionCatalog: array_values($this->promotionCatalog),
            kpi: [
                'totalTransactions' => $this->totalTransactions,
                'promoTransactions' => $this->promoTransactions,
                'promoAdoptionRate' => $this->promoAdoptionRate,
                'totalRevenue' => $this->totalRevenue,
                'promoRevenue' => $this->promoRevenue,
                'totalDiscount' => $this->totalDiscount,
                'avgPromoTransaction' => $this->avgPromoTransaction,
                'avgNonPromoTransaction' => $this->avgNonPromoTransaction,
            ],
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
            'totalRevenue' => 0.0,
            'totalTransactions' => 0,
            'promoRevenue' => 0.0,
            'promoTransactions' => 0,
            'nonPromoRevenue' => 0.0,
            'totalDiscount' => 0.0,
            'promoSales' => [],
            'promoByDate' => [],
            'branchPromo' => [],
        ];
    }

    /** @param array<string, mixed> $acc */
    private function accumulateSale(array &$acc, array $sale): void
    {
        $acc['hasData'] = true;
        $grandTotal = (float) ($sale['grandTotal'] ?? 0);
        $promoId = (int) ($sale['promotionID'] ?? 0);
        $promoName = trim((string) ($sale['promotionName'] ?? ''));
        // promotionDiscount is the configured rate (e.g. 10 for 10%), not the monetary amount.
        // Derive actual applied discount: discountTotal minus the non-promo components.
        $promoDis = max(0.0,
            (float) ($sale['discountTotal'] ?? 0)
            - (float) ($sale['menuDiscountTotal'] ?? 0)
            - (float) ($sale['voucherDiscountTotal'] ?? 0)
        );
        $date = $sale['salesDate'] ?? '';
        $branchCode = $sale['branchCode'] ?? 'Unknown';

        $acc['totalRevenue'] += $grandTotal;
        $acc['totalTransactions']++;
        $acc['totalDiscount'] += $promoDis;

        if ($promoName !== '') {
            $acc['promoRevenue'] += $grandTotal;
            $acc['promoTransactions']++;

            $key = $promoId ?: $promoName;
            if (! isset($acc['promoSales'][$key])) {
                $acc['promoSales'][$key] = [
                    'id' => $promoId,
                    'name' => $promoName,
                    'count' => 0,
                    'totalDiscount' => 0.0,
                    'totalRevenue' => 0.0,
                ];
            }
            $acc['promoSales'][$key]['count']++;
            $acc['promoSales'][$key]['totalDiscount'] += $promoDis;
            $acc['promoSales'][$key]['totalRevenue'] += $grandTotal;

            $acc['promoByDate'][$date] = ($acc['promoByDate'][$date] ?? 0) + 1;

            if (! isset($acc['branchPromo'][$branchCode])) {
                $acc['branchPromo'][$branchCode] = [];
            }
            $acc['branchPromo'][$branchCode][$promoName] = ($acc['branchPromo'][$branchCode][$promoName] ?? 0) + 1;
        } else {
            $acc['nonPromoRevenue'] += $grandTotal;
        }
    }

    /** @param array<string, mixed> $acc */
    private function finalizeData(array $acc): void
    {
        $this->totalTransactions = $acc['totalTransactions'];
        $this->totalRevenue = $acc['totalRevenue'];
        $this->promoTransactions = $acc['promoTransactions'];
        $this->promoRevenue = $acc['promoRevenue'];
        $this->totalDiscount = $acc['totalDiscount'];
        $this->promoAdoptionRate = $acc['totalTransactions'] > 0
            ? round($acc['promoTransactions'] / $acc['totalTransactions'] * 100, 1)
            : 0.0;
        $this->avgPromoTransaction = $acc['promoTransactions'] > 0
            ? $acc['promoRevenue'] / $acc['promoTransactions']
            : 0.0;
        $nonPromoCount = $acc['totalTransactions'] - $acc['promoTransactions'];
        $this->avgNonPromoTransaction = $nonPromoCount > 0
            ? $acc['nonPromoRevenue'] / $nonPromoCount
            : 0.0;

        uasort($acc['promoSales'], fn ($a, $b) => $b['count'] <=> $a['count']);
        ksort($acc['promoByDate']);

        // Build performance table — merge catalog metadata with sales stats
        $today = now()->toDateString();
        $this->promoPerformanceTable = [];
        $usedIds = [];

        foreach ($acc['promoSales'] as $stats) {
            $catalog = $this->promotionCatalog[$stats['id']] ?? null;
            $endDate = $catalog ? substr((string) ($catalog['endDate'] ?? ''), 0, 10) : null;
            $isActive = $endDate ? $endDate >= $today : true;
            $usedIds[] = $stats['id'];

            $this->promoPerformanceTable[] = [
                'id' => $stats['id'],
                'name' => $stats['name'],
                'code' => $catalog['promotionCode'] ?? '—',
                'type' => $catalog['promotionTypeDesc'] ?? '—',
                'configDiscount' => (float) ($catalog['discount'] ?? 0),
                'startDate' => $catalog ? substr((string) ($catalog['startDate'] ?? ''), 0, 10) : null,
                'endDate' => $endDate,
                'count' => $stats['count'],
                'totalDiscount' => $stats['totalDiscount'],
                'totalRevenue' => $stats['totalRevenue'],
                'avgTransaction' => $stats['count'] > 0 ? $stats['totalRevenue'] / $stats['count'] : 0.0,
                'adoptionRate' => $acc['totalTransactions'] > 0
                    ? round($stats['count'] / $acc['totalTransactions'] * 100, 1) : 0.0,
                'isActive' => $isActive,
            ];
        }

        // Catalog promos with 0 usage in this period
        foreach ($this->promotionCatalog as $promo) {
            $id = (int) ($promo['promotionID'] ?? 0);
            if (in_array($id, $usedIds, true)) {
                continue;
            }
            $endDate = substr((string) ($promo['endDate'] ?? ''), 0, 10);
            $this->promoPerformanceTable[] = [
                'id' => $id,
                'name' => $promo['promotionCode'] ?? 'Unknown',
                'code' => $promo['promotionCode'] ?? '—',
                'type' => $promo['promotionTypeDesc'] ?? '—',
                'configDiscount' => (float) ($promo['discount'] ?? 0),
                'startDate' => substr((string) ($promo['startDate'] ?? ''), 0, 10) ?: null,
                'endDate' => $endDate ?: null,
                'count' => 0,
                'totalDiscount' => 0.0,
                'totalRevenue' => 0.0,
                'avgTransaction' => 0.0,
                'adoptionRate' => 0.0,
                'isActive' => $endDate ? $endDate >= $today : true,
            ];
        }

        // Charts
        $top15 = array_slice(array_values($acc['promoSales']), 0, 15);
        $this->chartPromoUsage = [
            'labels' => array_column($top15, 'name'),
            'data' => array_column($top15, 'count'),
        ];
        $this->chartPromoDiscount = [
            'labels' => array_column($top15, 'name'),
            'data' => array_column($top15, 'totalDiscount'),
        ];
        $this->chartPromoTrend = [
            'labels' => array_keys($acc['promoByDate']),
            'data' => array_values($acc['promoByDate']),
        ];

        $this->branchPromoTable = $acc['branchPromo'];
    }
}
