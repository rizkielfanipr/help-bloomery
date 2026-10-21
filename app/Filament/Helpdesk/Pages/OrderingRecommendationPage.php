<?php

namespace App\Filament\Helpdesk\Pages;

use App\Actions\Ordering\ExportOrderingRecommendationsXlsxAction;
use App\Enums\OrderingConfidence;
use App\Models\Brand;
use App\Models\OrderingForecastItem;
use App\Models\OrderingForecastRun;
use App\Services\Ordering\OrderingRecommendationQuery;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Illuminate\Validation\Rule;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use UnitEnum;

/**
 * Analytics → Ordering Recommendation (docs/ordering-recommendation-prd.md §17–18). View-only:
 * it reads the latest completed local forecast runs through OrderingRecommendationQuery and never
 * calls ESB. Public state holds filters, the active branch, and the opened detail ID only; every
 * branch ID from the browser is intersected with the user's branch scope on each request.
 */
class OrderingRecommendationPage extends Page
{
    use WithPagination;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static string|UnitEnum|null $navigationGroup = 'Analytics';

    protected static ?string $navigationLabel = 'Ordering Recommendation';

    protected static ?string $title = 'Ordering Recommendation';

    protected static ?string $slug = 'ordering-recommendation';

    protected string $view = 'filament.helpdesk.pages.ordering-recommendation-page';

    /** @var list<int> */
    public array $brandIds = [];

    /** @var list<int> */
    public array $branchIds = [];

    public string $period = '30';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public int $horizon = 7;

    public ?string $category = null;

    public ?string $confidence = null;

    public ?string $risk = null;

    public string $search = '';

    /** @var array<string, mixed> */
    public array $applied = [];

    public ?int $activeBranchId = null;

    public string $rankingType = 'priority';

    public ?string $chartUom = null;

    public string $sort = 'priority';

    public string $direction = 'desc';

    public ?int $detailItemId = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view ordering recommendations') ?? false;
    }

    public function mount(): void
    {
        $this->horizon = (int) config('ordering_recommendation.default_horizon');
        $this->branchIds = $this->query()->resolveBranchIds(auth()->user(), []);
        $this->applyFilters();
    }

    public function updatedBrandIds(): void
    {
        $this->branchIds = [];
    }

    public function applyFilters(): void
    {
        $this->validate([
            'period' => ['required', Rule::in(['7', '30', 'custom'])],
            'dateFrom' => ['nullable', 'required_if:period,custom', 'date'],
            'dateTo' => ['nullable', 'required_if:period,custom', 'date', 'after_or_equal:dateFrom'],
            'horizon' => ['required', 'integer', Rule::in(config('ordering_recommendation.horizons'))],
            'confidence' => ['nullable', Rule::enum(OrderingConfidence::class)],
            'risk' => ['nullable', Rule::in(['stockout', 'need_order', 'blocked', 'overstock'])],
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:191'],
        ]);

        $branchIds = $this->query()->resolveBranchIds(auth()->user(), $this->branchIds, $this->brandIds);
        $this->branchIds = $branchIds;
        $this->applied = [
            'branch_ids' => $branchIds,
            'horizon' => $this->horizon,
            'period' => $this->period,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'filters' => ['category' => $this->category, 'confidence' => $this->confidence, 'risk' => $this->risk, 'search' => trim($this->search)],
        ];
        $this->activeBranchId = $branchIds[0] ?? null;
        $this->chartUom = null;
        $this->detailItemId = null;
        $this->resetPage('itemsPage');
    }

    public function resetFilters(): void
    {
        $this->reset(['brandIds', 'category', 'confidence', 'risk', 'search', 'dateFrom', 'dateTo']);
        $this->period = '30';
        $this->horizon = (int) config('ordering_recommendation.default_horizon');
        $this->branchIds = $this->query()->resolveBranchIds(auth()->user(), []);
        $this->applyFilters();
    }

    public function selectBranch(int $branchId): void
    {
        if (in_array($branchId, $this->scopedBranchIds(), true)) {
            $this->activeBranchId = $branchId;
            $this->chartUom = null;
        }
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, OrderingRecommendationQuery::SORTS, true)) {
            return;
        }
        $this->direction = $this->sort === $column && $this->direction === 'desc' ? 'asc' : 'desc';
        $this->sort = $column;
        $this->resetPage('itemsPage');
    }

    public function openDetail(int $itemId): void
    {
        abort_unless($this->scopedItems()->whereKey($itemId)->exists(), 404);
        $this->detailItemId = $itemId;
        $this->dispatch('open-modal', id: 'ordering-recommendation-detail');
    }

    public function closeDetail(): void
    {
        $this->detailItemId = null;
    }

    public function export(ExportOrderingRecommendationsXlsxAction $export): BinaryFileResponse
    {
        abort_unless(auth()->user()?->can('export ordering recommendations'), 403);

        return $export->execute(auth()->user(), $this->scopedBranchIds(), (int) $this->applied['horizon'], $this->applied['filters']);
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $query = $this->query();
        $user = auth()->user();
        $filters = $this->applied['filters'] ?? [];
        $branchIds = $this->scopedBranchIds();
        $runs = $query->latestRuns($branchIds, (int) ($this->applied['horizon'] ?? $this->horizon));
        $runIds = $runs->pluck('id')->all();
        $activeRun = $runs->get($this->activeBranchId) ?? $runs->first();
        [$from, $to] = $this->displayPeriod($activeRun);

        $detail = null;
        if ($this->detailItemId !== null) {
            $detail = $this->scopedItems()->with(['run:id,history_from,history_to,forecast_from,forecast_to,algorithm_version', 'branch:id,name'])->find($this->detailItemId);
        }

        return [
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'branchOptions' => $query->accessibleBranches($user)->when($this->brandIds !== [], fn ($q) => $q->whereIn('brand_id', $this->brandIds))->get(['id', 'name']),
            'categoryOptions' => OrderingForecastItem::query()->whereIn('ordering_forecast_run_id', $runIds === [] ? [0] : $runIds)->whereNotNull('category_name')->distinct()->orderBy('category_name')->pluck('category_name'),
            'horizons' => config('ordering_recommendation.horizons'),
            'selectedBranchCount' => count($branchIds),
            'runs' => $runs,
            'activeRun' => $activeRun,
            'kpis' => $query->kpis($runIds, $filters),
            'overview' => $runs->count() > 1 ? $query->branchOverview($runs, $filters) : [],
            'sourceState' => $activeRun ? $query->sourceState($activeRun) : null,
            'ranking' => $activeRun ? $query->ranking($activeRun->id, $this->rankingType, $filters, (int) config('ordering_recommendation.ranking_limit')) : collect(),
            'demandChart' => $activeRun ? $query->demandChart($activeRun, $this->chartUom, $from, $to) : null,
            'coverageChart' => $activeRun ? $query->coverageDistribution($activeRun->id, $filters) : [],
            'trendChart' => $activeRun ? $query->ranking($activeRun->id, 'trend_up', $filters, (int) config('ordering_recommendation.chart_limit')) : collect(),
            'wasteChart' => $activeRun ? $query->ranking($activeRun->id, 'waste', $filters, (int) config('ordering_recommendation.chart_limit')) : collect(),
            'items' => $query->items($runIds, $filters, $this->sort, $this->direction)->with('branch:id,name')->paginate(25, pageName: 'itemsPage'),
            'detail' => $detail,
            'canExport' => $user->can('export ordering recommendations'),
            'highWasteRate' => (float) config('ordering_recommendation.high_waste_rate'),
        ];
    }

    /**
     * Applied branches re-checked against the user's scope on every request.
     *
     * @return list<int>
     */
    private function scopedBranchIds(): array
    {
        return $this->query()->resolveBranchIds(auth()->user(), $this->applied['branch_ids'] ?? []);
    }

    private function scopedItems()
    {
        $runIds = $this->query()->latestRuns($this->scopedBranchIds(), (int) ($this->applied['horizon'] ?? $this->horizon))->pluck('id')->all();

        return OrderingForecastItem::query()->whereIn('ordering_forecast_run_id', $runIds === [] ? [0] : $runIds);
    }

    /** @return array{0: string, 1: string} */
    private function displayPeriod(?OrderingForecastRun $run): array
    {
        $end = $run?->history_to?->toDateString() ?? now()->subDay()->toDateString();

        return match ($this->applied['period'] ?? '30') {
            'custom' => [(string) $this->applied['date_from'], (string) $this->applied['date_to']],
            '7' => [CarbonImmutable::parse($end)->subDays(6)->toDateString(), $end],
            default => [CarbonImmutable::parse($end)->subDays(29)->toDateString(), $end],
        };
    }

    private function query(): OrderingRecommendationQuery
    {
        return app(OrderingRecommendationQuery::class);
    }
}
