<?php

namespace App\Filament\Helpdesk\Pages;

use App\Models\Branch;
use App\Services\EsbService;
use BackedEnum;
use Carbon\CarbonPeriod;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

class StockInformationPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Analytics';

    protected string $view = 'filament.helpdesk.pages.stock-information-page';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view stock information') ?? false;
    }

    /** @var list<int> */
    public array $selectedBranchIds = [];

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public bool $fetched = false;

    public bool $isFetching = false;

    /** @var list<int> */
    public array $fetchBranchIds = [];

    public int $fetchBranchIndex = 0;

    public string $fetchCurrentBranchCode = '';

    /** @var array<string, mixed> */
    public array $fetchAcc = [];

    // ── KPIs ──────────────────────────────────────────────────────────────
    public int $totalRecords = 0;

    public int $totalBranches = 0;

    public string $topMaterial = '';

    public float $totalQty = 0.0;

    // ── Charts ────────────────────────────────────────────────────────────
    /** @var array{labels: list<string>, data: list<float>} */
    public array $chartTrend = ['labels' => [], 'data' => []];

    /** @var array{labels: list<string>, data: list<float>} */
    public array $chartTopMaterials = ['labels' => [], 'data' => []];

    /** @var array{labels: list<string>, data: list<float>} */
    public array $chartBranchComparison = ['labels' => [], 'data' => []];

    /** @var array{labels: list<string>, data: list<float>} */
    public array $chartDistribution = ['labels' => [], 'data' => []];

    // ── Table ─────────────────────────────────────────────────────────────
    /** @var list<array{name: string, unit: string, totalQty: float, branchCount: int, avgPerDay: float}> */
    public array $materialTable = [];

    public function mount(): void
    {
        $this->dateFrom = today()->toDateString();
    }

    public function getTitle(): string
    {
        return 'Stock Information';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    /** @return list<Branch> */
    public function getAllBranches(): array
    {
        return Branch::whereNotNull('esb_branch_code')
            ->whereNotNull('esb_comcode')
            ->orderBy('name')
            ->get()
            ->all();
    }

    public function toggleBranchId(int $id): void
    {
        if (in_array($id, $this->selectedBranchIds)) {
            $this->selectedBranchIds = array_values(array_filter($this->selectedBranchIds, fn ($v) => $v !== $id));
        } else {
            $this->selectedBranchIds[] = $id;
        }
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
            $branchIds = collect($this->getAllBranches())
                ->filter(fn (Branch $b) => ! empty($b->esb_token))
                ->pluck('id')
                ->values()
                ->all();
        }

        if (empty($branchIds)) {
            Notification::make()->title('Tidak ada cabang dengan token ESB yang dikonfigurasi')->warning()->send();

            return;
        }

        $this->fetched = false;
        $this->isFetching = true;
        $this->fetchBranchIds = $branchIds;
        $this->fetchBranchIndex = 0;
        $this->fetchAcc = $this->initAcc();

        $this->dispatch('stock-fetch-next');
    }

    public function fetchNextBranch(): void
    {
        if (! $this->isFetching) {
            return;
        }

        $branchId = $this->fetchBranchIds[$this->fetchBranchIndex] ?? null;

        if ($branchId === null) {
            $this->finishFetch();

            return;
        }

        $branch = Branch::find($branchId);

        if (! $branch || ! $branch->esb_token) {
            $this->advanceBranch();

            return;
        }

        $this->fetchCurrentBranchCode = $branch->esb_branch_code ?? $branch->name;

        $dateFrom = $this->dateFrom ?? today()->toDateString();
        $dateTo = $this->dateTo;

        $esb = new EsbService;
        $acc = $this->fetchAcc;

        foreach (CarbonPeriod::create($dateFrom, $dateTo) as $day) {
            try {
                $rows = $esb->getDailySalesMaterialUsage(
                    $branch->esb_branch_code,
                    $day->toDateString(),
                    'stockUnit',
                    $branch->esb_token,
                );
                foreach ($rows as $row) {
                    $this->accumulateRow($acc, $row, $branch->name, $day->toDateString());
                }
            } catch (\RuntimeException) {
                // skip failed dates
            }
        }

        $this->fetchAcc = $acc;
        $this->advanceBranch();
    }

    private function advanceBranch(): void
    {
        $this->fetchBranchIndex++;

        if ($this->fetchBranchIndex < count($this->fetchBranchIds)) {
            $this->dispatch('stock-fetch-next');
        } else {
            $this->finishFetch();
        }
    }

    private function finishFetch(): void
    {
        if (! $this->fetchAcc['hasData']) {
            Notification::make()->title('Tidak ada data material dari ESB pada periode ini')->info()->send();
            $this->isFetching = false;
            $this->fetchAcc = [];

            return;
        }

        $this->finalizeData($this->fetchAcc);
        $this->isFetching = false;
        $this->fetchAcc = [];
        $this->fetched = true;

        $this->dispatch('stock-loaded',
            trend: $this->chartTrend,
            topMaterials: $this->chartTopMaterials,
            branchComparison: $this->chartBranchComparison,
            distribution: $this->chartDistribution,
            materialTable: $this->materialTable,
            kpi: [
                'totalRecords' => $this->totalRecords,
                'totalBranches' => $this->totalBranches,
                'topMaterial' => $this->topMaterial,
                'totalQty' => $this->totalQty,
            ],
        );
    }

    /** @return array<string, mixed> */
    private function initAcc(): array
    {
        return [
            'hasData' => false,
            'byDate' => [],
            'byMat' => [],
            'byBranch' => [],
            'branches' => [],
            'dates' => [],
        ];
    }

    /** @param array<string, mixed> $acc */
    private function accumulateRow(array &$acc, array $row, string $branchName, string $date): void
    {
        $name = trim((string) ($row['productName'] ?? ''));
        $unit = (string) ($row['unit'] ?? 'PCS');
        $qty = (float) ($row['totalQty'] ?? 0);

        if ($name === '' || $qty <= 0) {
            return;
        }

        $acc['hasData'] = true;
        $acc['dates'][$date] = true;
        $acc['byDate'][$date] = ($acc['byDate'][$date] ?? 0.0) + $qty;

        if (! isset($acc['byMat'][$name])) {
            $acc['byMat'][$name] = ['name' => $name, 'unit' => $unit, 'totalQty' => 0.0, 'branches' => []];
        }
        $acc['byMat'][$name]['totalQty'] += $qty;
        $acc['byMat'][$name]['branches'][$branchName] = true;

        $acc['byBranch'][$branchName] = ($acc['byBranch'][$branchName] ?? 0.0) + $qty;
        $acc['branches'][$branchName] = true;
    }

    /** @param array<string, mixed> $acc */
    private function finalizeData(array $acc): void
    {
        ksort($acc['byDate']);
        uasort($acc['byMat'], fn ($a, $b) => $b['totalQty'] <=> $a['totalQty']);
        arsort($acc['byBranch']);

        $dateCount = max(1, count($acc['dates']));
        $totalQty = (float) array_sum(array_column($acc['byMat'], 'totalQty'));

        $this->totalRecords = (int) array_sum(array_map(fn ($m) => count($m['branches']), $acc['byMat']));
        $this->totalBranches = count($acc['branches']);
        $this->topMaterial = (string) (array_key_first($acc['byMat']) ?? '');
        $this->totalQty = round($totalQty, 2);

        $this->chartTrend = [
            'labels' => array_keys($acc['byDate']),
            'data' => array_map(fn ($v) => round($v, 2), array_values($acc['byDate'])),
        ];

        $top10 = array_slice($acc['byMat'], 0, 10, true);
        $this->chartTopMaterials = [
            'labels' => array_keys($top10),
            'data' => array_map(fn ($m) => round($m['totalQty'], 4), array_values($top10)),
        ];

        $this->chartBranchComparison = [
            'labels' => array_keys($acc['byBranch']),
            'data' => array_map(fn ($v) => round($v, 2), array_values($acc['byBranch'])),
        ];

        $top8 = array_slice($acc['byMat'], 0, 8, true);
        $top8Qty = (float) array_sum(array_column($top8, 'totalQty'));
        $otherQty = $totalQty - $top8Qty;
        $dLabels = array_keys($top8);
        $dData = array_map(fn ($m) => round($m['totalQty'], 2), array_values($top8));
        if ($otherQty > 0.001) {
            $dLabels[] = 'Lainnya';
            $dData[] = round($otherQty, 2);
        }
        $this->chartDistribution = ['labels' => $dLabels, 'data' => $dData];

        $this->materialTable = array_values(array_map(fn ($m) => [
            'name' => $m['name'],
            'unit' => $m['unit'],
            'totalQty' => round($m['totalQty'], 4),
            'branchCount' => count($m['branches']),
            'avgPerDay' => round($m['totalQty'] / $dateCount, 4),
        ], array_slice($acc['byMat'], 0, 10, true)));
    }
}
