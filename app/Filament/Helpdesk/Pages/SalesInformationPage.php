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

    /**
     * Accumulator carried across paged-fetch requests.
     *
     * @var array{hasData: bool, revenue: float, transactions: int, items: int, byDate: array<string, float>, menuQty: array<string, int>, paymentRows: array<string, array{name: string, type: string, total: float}>, hourly: list<int>, categoryRevenue: array<string, float>, visitPurpose: array<string, int>}
     */
    public array $fetchAcc = [];

    // Final display state
    public float $totalRevenue = 0;

    public int $totalTransactions = 0;

    public float $avgTransaction = 0;

    public int $totalItems = 0;

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

    /** @var array{labels: list<string>, data: list<int>} */
    public array $chartVisitPurpose = ['labels' => [], 'data' => []];

    /** @var list<array{name: string, type: string, total: float}> */
    public array $paymentTable = [];

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
            $this->validate([
                'selectedBranchId' => ['integer', 'exists:branches,id'],
            ]);

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
            visitPurpose: $this->chartVisitPurpose,
        );
    }

    private function stopFetch(): void
    {
        $this->isFetching = false;
        $this->fetchAcc = [];
    }

    /**
     * @return array{hasData: bool, revenue: float, transactions: int, items: int, byDate: array<string, float>, menuQty: array<string, int>, paymentRows: array<string, array{name: string, type: string, total: float}>, hourly: list<int>, categoryRevenue: array<string, float>, visitPurpose: array<string, int>}
     */
    private function initAcc(): array
    {
        return [
            'hasData' => false,
            'revenue' => 0.0,
            'transactions' => 0,
            'items' => 0,
            'byDate' => [],
            'menuQty' => [],
            'paymentRows' => [],
            'hourly' => array_fill(0, 24, 0),
            'categoryRevenue' => [],
            'visitPurpose' => [],
        ];
    }

    /** @param array{hasData: bool, revenue: float, transactions: int, items: int, byDate: array<string, float>, menuQty: array<string, int>, paymentRows: array<string, array{name: string, type: string, total: float}>, hourly: list<int>, categoryRevenue: array<string, float>, visitPurpose: array<string, int>} $acc */
    private function accumulateSale(array &$acc, array $sale): void
    {
        $acc['hasData'] = true;
        $acc['revenue'] += (float) ($sale['grandTotal'] ?? 0);
        $acc['transactions']++;

        $date = $sale['salesDate'] ?? '';
        $acc['byDate'][$date] = ($acc['byDate'][$date] ?? 0.0) + (float) ($sale['grandTotal'] ?? 0);

        if ($dateIn = $sale['salesDateIn'] ?? null) {
            $acc['hourly'][(int) date('G', strtotime($dateIn))]++;
        }

        $purpose = $sale['visitPurposeName'] ?: 'Lainnya';
        $acc['visitPurpose'][$purpose] = ($acc['visitPurpose'][$purpose] ?? 0) + 1;

        foreach ($sale['salesMenus'] ?? [] as $menu) {
            $qty = (int) ($menu['qty'] ?? 0);
            $acc['items'] += $qty;
            $name = $menu['menuName'] ?? 'Unknown';
            $acc['menuQty'][$name] = ($acc['menuQty'][$name] ?? 0) + $qty;
            $cat = $menu['menuCategoryName'] ?: 'Lainnya';
            $acc['categoryRevenue'][$cat] = ($acc['categoryRevenue'][$cat] ?? 0.0) + (float) ($menu['total'] ?? 0);
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

    /** @param array{hasData: bool, revenue: float, transactions: int, items: int, byDate: array<string, float>, menuQty: array<string, int>, paymentRows: array<string, array{name: string, type: string, total: float}>, hourly: list<int>, categoryRevenue: array<string, float>, visitPurpose: array<string, int>} $acc */
    private function finalizeData(array $acc): void
    {
        $this->totalRevenue = $acc['revenue'];
        $this->totalTransactions = $acc['transactions'];
        $this->avgTransaction = $acc['transactions'] > 0 ? $acc['revenue'] / $acc['transactions'] : 0;
        $this->totalItems = $acc['items'];

        ksort($acc['byDate']);
        arsort($acc['menuQty']);
        uasort($acc['paymentRows'], fn ($a, $b) => $b['total'] <=> $a['total']);
        arsort($acc['categoryRevenue']);
        arsort($acc['visitPurpose']);

        $top10 = array_slice($acc['menuQty'], 0, 10, true);

        $this->paymentTable = array_values($acc['paymentRows']);

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
        $this->chartVisitPurpose = ['labels' => array_keys($acc['visitPurpose']), 'data' => array_values($acc['visitPurpose'])];
    }
}
