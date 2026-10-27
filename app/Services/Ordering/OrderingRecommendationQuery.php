<?php

namespace App\Services\Ordering;

use App\Enums\OrderingConfidence;
use App\Enums\OrderingSource;
use App\Enums\OrderingSyncStatus;
use App\Enums\OrderingTrendLabel;
use App\Models\Branch;
use App\Models\OrderingDailyMetric;
use App\Models\OrderingForecastItem;
use App\Models\OrderingForecastRun;
use App\Models\OrderingSyncRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Read model of Ordering Recommendation (docs/ordering-recommendation-prd.md §20, §22). Every
 * read starts from the branches the user may access; branch IDs coming from the browser are only
 * ever intersected with that scope. Results come from the latest completed forecast run per
 * branch and horizon. Quantities are never summed across UOMs.
 */
class OrderingRecommendationQuery
{
    public const SORTS = ['priority', 'recommendation', 'forecast', 'coverage', 'trend', 'waste', 'product'];

    public const RANKINGS = ['priority', 'top_demand', 'trend_up', 'trend_down', 'waste', 'stockout'];

    public function accessibleBranches(User $user): Builder
    {
        return Branch::query()
            ->whereHas('esbCodes', fn (Builder $query): Builder => $query->where('is_active', true))
            ->when(! $user->canAccessAllBranches(), fn (Builder $query): Builder => $query->whereIn('id', $user->accessibleBranchIds()))
            ->orderBy('name');
    }

    /**
     * Requested branches limited to the user's scope and brands; defaults to the primary branch.
     *
     * @param  list<int>  $requested
     * @param  list<int>  $brandIds
     * @return list<int>
     */
    public function resolveBranchIds(User $user, array $requested, array $brandIds = []): array
    {
        $allowed = $this->accessibleBranches($user)
            ->when($brandIds !== [], fn (Builder $query): Builder => $query->whereIn('brand_id', $brandIds))
            ->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $requested = array_values(array_intersect(array_map('intval', $requested), $allowed));
        if ($requested !== []) {
            return $requested;
        }

        return in_array((int) $user->branch_id, $allowed, true) ? [(int) $user->branch_id] : array_slice($allowed, 0, 1);
    }

    /**
     * Latest completed run per branch for the horizon.
     *
     * @param  list<int>  $branchIds
     * @return Collection<int, OrderingForecastRun>
     */
    public function latestRuns(array $branchIds, int $horizon): Collection
    {
        if ($branchIds === []) {
            return collect();
        }

        return OrderingForecastRun::query()
            ->whereIn('id', OrderingForecastRun::query()->selectRaw('MAX(id)')
                ->whereIn('branch_id', $branchIds)->where('horizon_days', $horizon)
                ->where('status', OrderingSyncStatus::Completed->value)->groupBy('branch_id'))
            ->with('branch:id,name')
            ->get()
            ->keyBy('branch_id');
    }

    /**
     * @param  list<int>  $runIds
     * @param  array{category?: ?string, confidence?: ?string, risk?: ?string, search?: ?string}  $filters
     */
    public function items(array $runIds, array $filters, string $sort = 'priority', string $direction = 'desc'): Builder
    {
        $direction = $direction === 'asc' ? 'asc' : 'desc';
        $query = OrderingForecastItem::query()
            ->whereIn('ordering_forecast_run_id', $runIds === [] ? [0] : $runIds)
            ->when(filled($filters['category'] ?? null), fn (Builder $query): Builder => $query->where('category_name', $filters['category']))
            ->when(filled($filters['confidence'] ?? null), fn (Builder $query): Builder => $query->where('confidence', $filters['confidence']))
            ->when(filled($filters['search'] ?? null), function (Builder $query) use ($filters): Builder {
                $term = '%'.addcslashes(trim((string) $filters['search']), '%_\\').'%';

                return $query->where(fn (Builder $query): Builder => $query->where('product_code', 'like', $term)->orWhere('product_name', 'like', $term));
            })
            ->when(filled($filters['risk'] ?? null), fn (Builder $query): Builder => match ($filters['risk']) {
                'stockout' => $query->whereNotNull('predicted_stockout_date'),
                'need_order' => $query->where('final_recommendation', '>', 0),
                'blocked' => $query->where('confidence', OrderingConfidence::Blocked->value),
                'overstock' => $query->where('final_recommendation', 0)->where('coverage_days', '>', max(config('ordering_recommendation.horizons'))),
                default => $query,
            });

        $column = match (in_array($sort, self::SORTS, true) ? $sort : 'priority') {
            'recommendation' => 'final_recommendation',
            'forecast' => 'forecast_demand',
            'coverage' => 'coverage_days',
            'trend' => 'trend_final',
            'waste' => 'waste_rate_capped',
            'product' => 'product_name',
            default => 'priority_score',
        };

        // Rows without a value (Blocked, no demand) always sort last.
        return $query->orderByRaw("{$column} IS NULL")->orderBy($column, $direction)->orderBy('id');
    }

    /**
     * KPI counts in one aggregate query — never a sum of quantities across UOMs.
     *
     * @param  list<int>  $runIds
     * @param  array<string, mixed>  $filters
     * @return array{products: int, need_order: int, stockout: int, trend_up: int, high_waste: int, incomplete: int}
     */
    public function kpis(array $runIds, array $filters): array
    {
        $row = $this->items($runIds, $filters)->reorder()->toBase()->selectRaw(
            'COUNT(*) as products,
            SUM(CASE WHEN final_recommendation > 0 THEN 1 ELSE 0 END) as need_order,
            SUM(CASE WHEN predicted_stockout_date IS NOT NULL THEN 1 ELSE 0 END) as stockout,
            SUM(CASE WHEN trend_label = ? THEN 1 ELSE 0 END) as trend_up,
            SUM(CASE WHEN waste_rate_capped >= ? THEN 1 ELSE 0 END) as high_waste,
            SUM(CASE WHEN confidence = ? OR reason_codes LIKE ? OR reason_codes LIKE ? THEN 1 ELSE 0 END) as incomplete',
            [OrderingTrendLabel::Rising->value, (float) config('ordering_recommendation.high_waste_rate'), OrderingConfidence::Blocked->value, '%usage_partial%', '%stock_partial%'],
        )->first();

        return collect(['products', 'need_order', 'stockout', 'trend_up', 'high_waste', 'incomplete'])
            ->mapWithKeys(fn (string $key): array => [$key => (int) ($row->{$key} ?? 0)])->all();
    }

    /**
     * One summary per branch: its own counts, top rising and top waste product, confidence spread,
     * and source state.
     *
     * @param  Collection<int, OrderingForecastRun>  $runs
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function branchOverview(Collection $runs, array $filters): array
    {
        return $runs->map(function (OrderingForecastRun $run) use ($filters): array {
            $kpis = $this->kpis([$run->id], $filters);
            $confidence = $this->items([$run->id], $filters)->reorder()->toBase()->selectRaw('confidence, COUNT(*) as total')->groupBy('confidence')->pluck('total', 'confidence');

            return [
                'branch_id' => $run->branch_id,
                'branch' => $run->branch?->name,
                'run_id' => $run->id,
                'kpis' => $kpis,
                'top_rising' => $this->ranking($run->id, 'trend_up', $filters, 1)->first(),
                'top_waste' => $this->ranking($run->id, 'waste', $filters, 1)->first(),
                'confidence' => collect(OrderingConfidence::cases())->mapWithKeys(fn (OrderingConfidence $level): array => [$level->value => (int) ($confidence[$level->value] ?? 0)])->all(),
                'source_state' => $this->sourceState($run),
            ];
        })->values()->all();
    }

    /**
     * Ranking inside one branch run (key: branch + period + type).
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, OrderingForecastItem>
     */
    public function ranking(int $runId, string $type, array $filters, int $limit): Collection
    {
        $query = $this->items([$runId], $filters)->reorder();

        return (match ($type) {
            'top_demand' => $query->whereNotNull('historical_demand')->where('historical_demand', '>', 0)->orderByDesc('average_daily_demand'),
            'trend_up' => $query->where('trend_label', OrderingTrendLabel::Rising->value)->orderByDesc('trend_final'),
            'trend_down' => $query->where('trend_label', OrderingTrendLabel::Falling->value)->orderBy('trend_final'),
            'waste' => $query->whereNotNull('waste_rate_capped')->where('waste_rate_capped', '>', 0)->orderByDesc('waste_rate_capped'),
            'stockout' => $query->whereNotNull('predicted_stockout_date')->orderBy('predicted_stockout_date')->orderBy('coverage_days'),
            default => $query->whereNotNull('priority_score')->orderByDesc('priority_score'),
        })->orderBy('id')->limit($limit)->get();
    }

    /**
     * Actual demand over the display period and the forecast after the cutoff, for one branch and
     * one UOM only.
     *
     * @return array{labels: list<string>, actual: list<?float>, forecast: list<?float>, uoms: list<string>, uom: ?string}
     */
    public function demandChart(OrderingForecastRun $run, ?string $uom, string $from, string $to): array
    {
        $uoms = OrderingForecastItem::query()->where('ordering_forecast_run_id', $run->id)
            ->selectRaw('uom, COUNT(*) as total')->groupBy('uom')->orderByDesc('total')->pluck('uom')->all();
        $uom = in_array($uom, $uoms, true) ? $uom : ($uoms[0] ?? null);
        if ($uom === null) {
            return ['labels' => [], 'actual' => [], 'forecast' => [], 'uoms' => [], 'uom' => null];
        }

        $identities = OrderingForecastItem::query()->where('ordering_forecast_run_id', $run->id)->where('uom', $uom)
            ->get(['company_code', 'esb_branch_code', 'product_code', 'daily_breakdown']);
        $actual = OrderingDailyMetric::query()
            ->where('branch_id', $run->branch_id)->where('uom', $uom)
            ->whereIn('product_code', $identities->pluck('product_code')->unique()->all())
            ->whereDate('metric_date', '>=', $from)->whereDate('metric_date', '<=', min($to, $run->history_to->toDateString()))
            ->toBase()->selectRaw('DATE(metric_date) as day, SUM(sales_driven_usage) as total')->groupByRaw('DATE(metric_date)')
            ->pluck('total', 'day');
        $forecast = [];
        foreach ($identities as $identity) {
            foreach ($identity->daily_breakdown ?? [] as $day) {
                $forecast[$day['date']] = ($forecast[$day['date']] ?? 0) + (float) $day['forecast'];
            }
        }

        $labels = collect(array_keys($actual->all()))->merge(array_keys($forecast))->unique()->sort()->values()->all();

        return [
            'labels' => $labels,
            'actual' => array_map(fn (string $date): ?float => isset($actual[$date]) ? round((float) $actual[$date], 2) : null, $labels),
            'forecast' => array_map(fn (string $date): ?float => isset($forecast[$date]) ? round($forecast[$date], 2) : null, $labels),
            'uoms' => $uoms,
            'uom' => $uom,
        ];
    }

    /**
     * Coverage buckets (product counts, unit-free) for one branch run.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function coverageDistribution(int $runId, array $filters): array
    {
        $row = $this->items([$runId], $filters)->reorder()->toBase()->selectRaw(
            "SUM(CASE WHEN confidence = 'blocked' THEN 1 ELSE 0 END) as blocked,
            SUM(CASE WHEN confidence <> 'blocked' AND coverage_days IS NULL THEN 1 ELSE 0 END) as no_demand,
            SUM(CASE WHEN coverage_days < 1 THEN 1 ELSE 0 END) as under_1,
            SUM(CASE WHEN coverage_days >= 1 AND coverage_days < 3 THEN 1 ELSE 0 END) as days_1_3,
            SUM(CASE WHEN coverage_days >= 3 AND coverage_days < 7 THEN 1 ELSE 0 END) as days_3_7,
            SUM(CASE WHEN coverage_days >= 7 AND coverage_days < 14 THEN 1 ELSE 0 END) as days_7_14,
            SUM(CASE WHEN coverage_days >= 14 THEN 1 ELSE 0 END) as over_14"
        )->first();

        return [
            '< 1 hari' => (int) $row->under_1, '1–3 hari' => (int) $row->days_1_3, '3–7 hari' => (int) $row->days_3_7,
            '7–14 hari' => (int) $row->days_7_14, '≥ 14 hari' => (int) $row->over_14,
            'Tidak ada demand' => (int) $row->no_demand, 'Blocked' => (int) $row->blocked,
        ];
    }

    /**
     * Plain label and explanation of a source state for every user, shared by the page and the export.
     *
     * @return array{label: string, description: string}
     */
    public static function stateText(string $state): array
    {
        $hours = (int) config('ordering_recommendation.stock_stale_hours');

        return match ($state) {
            'fresh' => ['label' => 'Data lengkap', 'description' => "Data penjualan dan stok dari ESB sudah lengkap dan diperbarui dalam {$hours} jam terakhir."],
            'partial' => ['label' => 'Data belum lengkap', 'description' => 'Ada hari yang datanya belum berhasil diambil dari ESB, jadi rekomendasi bisa kurang akurat.'],
            'stale' => ['label' => 'Data perlu diperbarui', 'description' => "Data terakhir diambil dari ESB lebih dari {$hours} jam yang lalu, jadi rekomendasi mungkin tidak sesuai kondisi saat ini."],
            default => ['label' => 'Data penjualan tidak tersedia', 'description' => 'Data pemakaian bahan dari ESB belum tersedia, jadi rekomendasi belum bisa dihitung.'],
        };
    }

    /**
     * Fresh / Stale / Partial / Failed per branch from the run's completeness and the latest sync.
     *
     * @return array{state: string, warnings: list<string>, last_synced_at: ?string, pairs: list<array<string, mixed>>}
     */
    public function sourceState(OrderingForecastRun $run): array
    {
        $completeness = $run->completeness ?? ['pairs' => [], 'warnings' => []];
        $pairs = $completeness['pairs'] ?? [];
        $lastSync = OrderingSyncRun::query()->where('branch_id', $run->branch_id)->where('status', OrderingSyncStatus::Completed->value)->max('finished_at');
        $staleHours = (int) config('ordering_recommendation.stock_stale_hours');

        $state = match (true) {
            $pairs === [] => 'failed',
            collect($pairs)->every(fn (array $pair): bool => ($pair['usage_valid_days'] ?? 0) === 0) => 'failed',
            collect($pairs)->contains(fn (array $pair): bool => ($pair['usage_missing_days'] ?? 0) > 0 || ($pair['failed_runs'] ?? 0) > 0) => 'partial',
            $lastSync === null || CarbonImmutable::parse($lastSync)->lt(now()->subHours($staleHours)) => 'stale',
            default => 'fresh',
        };

        return ['state' => $state, 'warnings' => $completeness['warnings'] ?? [], 'last_synced_at' => $lastSync ? CarbonImmutable::parse($lastSync)->toIso8601String() : null, 'pairs' => $pairs];
    }

    /**
     * Failed or partial source reads of the branches, for the Data Quality view/export.
     *
     * @param  list<int>  $branchIds
     */
    public function dataQuality(array $branchIds, string $from, string $to): Builder
    {
        return OrderingSyncRun::query()
            ->whereIn('branch_id', $branchIds === [] ? [0] : $branchIds)
            ->whereDate('period_from', '>=', $from)->whereDate('period_from', '<=', $to)
            ->whereIn('source', array_map(fn (OrderingSource $source): string => $source->value, OrderingSource::cases()))
            ->with('branch:id,name')
            ->orderBy('branch_id')->orderBy('period_from')->orderBy('id');
    }

    /**
     * Daily facts behind the selected recommendations.
     *
     * @param  list<int>  $branchIds
     */
    public function dailyHistory(array $branchIds, string $from, string $to): Builder
    {
        return OrderingDailyMetric::query()
            ->whereIn('branch_id', $branchIds === [] ? [0] : $branchIds)
            ->whereDate('metric_date', '>=', $from)->whereDate('metric_date', '<=', $to)
            ->with('branch:id,name')
            ->orderBy('id');
    }
}
