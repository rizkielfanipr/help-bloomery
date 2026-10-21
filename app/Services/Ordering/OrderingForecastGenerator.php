<?php

namespace App\Services\Ordering;

use App\Enums\OrderingSource;
use App\Enums\OrderingSyncStatus;
use App\Models\Branch;
use App\Models\BranchEsbCode;
use App\Models\OrderingDailyMetric;
use App\Models\OrderingForecastItem;
use App\Models\OrderingForecastRun;
use App\Models\OrderingSyncRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Builds one immutable forecast run for a branch from the local snapshot only — no ESB call
 * (docs/ordering-recommendation-prd.md §13, §21). The run is keyed by branch, cutoff, horizon,
 * algorithm version, and configuration hash: a rerun with the same inputs returns the existing
 * run, and a run becomes Completed only after all of its items are stored.
 *
 * Demand history keeps only days whose Daily Usage read completed for that company pair; a
 * product absent from a complete day used 0, a day that failed is left out. Each company pair and
 * UOM stays its own identity — nothing is merged by product name or across companies.
 */
class OrderingForecastGenerator
{
    public function __construct(
        private readonly OrderingForecastCalculator $calculator,
        private readonly OrderingBacktestService $backtest,
    ) {}

    public function generate(Branch $branch, string $cutoffDate, int $horizon): OrderingForecastRun
    {
        $config = $this->configuration();
        $hash = hash('sha256', json_encode($config, JSON_THROW_ON_ERROR));
        $cutoff = CarbonImmutable::parse($cutoffDate)->startOfDay();
        $key = implode(':', [$branch->id, $cutoff->toDateString(), $horizon, $config['algorithm_version'], $hash]);

        $existing = OrderingForecastRun::query()->where('idempotency_key', $key)->first();
        if ($existing?->status === OrderingSyncStatus::Completed) {
            return $existing;
        }

        $historyFrom = $cutoff->subDays($config['history_weeks'] * 7 - 1);
        $attributes = [
            'branch_id' => $branch->id,
            'horizon_days' => $horizon,
            'history_from' => $historyFrom->toDateString(),
            'history_to' => $cutoff->toDateString(),
            'forecast_from' => $cutoff->addDay()->toDateString(),
            'forecast_to' => $cutoff->addDays($horizon)->toDateString(),
            'algorithm_version' => $config['algorithm_version'],
            'configuration_hash' => $hash,
            'configuration_snapshot' => $config,
            'data_cutoff_at' => $cutoff->endOfDay(),
            'correlation_id' => (string) Str::uuid(),
        ];

        $startedAt = hrtime(true);

        try {
            [$items, $completeness] = $this->buildItems($branch, $cutoff, $historyFrom, $horizon, $config);

            $run = DB::transaction(function () use ($key, $attributes, $items, $completeness): OrderingForecastRun {
                $run = OrderingForecastRun::query()->updateOrCreate(['idempotency_key' => $key], [
                    ...$attributes, 'status' => OrderingSyncStatus::Running, 'completeness' => $completeness, 'error_summary' => null,
                ]);
                $run->items()->delete();
                foreach (array_chunk($items, 200) as $chunk) {
                    OrderingForecastItem::query()->insert(array_map(fn (array $item): array => [...$item, 'ordering_forecast_run_id' => $run->id], $chunk));
                }
                $run->update(['status' => OrderingSyncStatus::Completed, 'items_count' => count($items), 'generated_at' => now()]);

                return $run->refresh();
            });

            Log::info('Ordering forecast generated.', [
                'branch_id' => $branch->id, 'horizon' => $horizon, 'cutoff' => $cutoff->toDateString(), 'items' => count($items),
                'confidence' => collect($items)->countBy('confidence')->all(), 'duration_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
                'correlation_id' => $run->correlation_id,
            ]);

            return $run;
        } catch (Throwable $exception) {
            report($exception);

            return OrderingForecastRun::query()->updateOrCreate(['idempotency_key' => $key], [
                ...$attributes, 'status' => OrderingSyncStatus::Failed, 'items_count' => 0,
                'error_summary' => mb_substr('Forecast gagal: '.class_basename($exception), 0, 500),
            ]);
        }
    }

    /**
     * The configuration snapshot stored on (and hashed into) every run.
     *
     * @return array<string, mixed>
     */
    public function configuration(): array
    {
        return collect(config('ordering_recommendation'))->except(['sync_time', 'queue', 'ranking_limit', 'chart_limit'])->all();
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{0: list<array<string, mixed>>, 1: array<string, mixed>}
     */
    private function buildItems(Branch $branch, CarbonImmutable $cutoff, CarbonImmutable $historyFrom, int $horizon, array $config): array
    {
        $pairs = $branch->esbCodes()->where('is_active', true)->orderBy('id')->get();
        $runs = OrderingSyncRun::query()
            ->where('branch_id', $branch->id)
            ->whereDate('period_from', '>=', $historyFrom->toDateString())
            ->whereDate('period_from', '<=', $cutoff->toDateString())
            ->get(['source', 'company_code', 'esb_branch_code', 'period_from', 'status', 'finished_at']);
        $metrics = OrderingDailyMetric::query()
            ->where('branch_id', $branch->id)
            ->whereDate('metric_date', '>=', $historyFrom->toDateString())
            ->whereDate('metric_date', '<=', $cutoff->toDateString())
            ->get(['company_code', 'esb_branch_code', 'metric_date', 'product_code', 'product_detail_id', 'product_name', 'category_name', 'uom', 'sales_driven_usage', 'waste_quantity', 'ending_stock', 'stock_observed_at']);

        $windowDays = $config['history_weeks'] * 7;
        $eligible = $config['eligible_categories'] === null ? null : array_map('mb_strtolower', $config['eligible_categories']);
        $items = [];
        $completeness = ['pairs' => [], 'warnings' => []];
        if ($eligible === null) {
            $completeness['warnings'][] = 'category_unconfirmed';
        }
        if ($config['stock_location_scope'] === null) {
            $completeness['warnings'][] = 'stock_location_unconfirmed';
        }

        foreach ($pairs as $pair) {
            $pairRuns = $runs->filter(fn (OrderingSyncRun $run): bool => $run->company_code === $pair->esb_comcode && $run->esb_branch_code === $pair->esb_branch_code);
            $usageDays = $this->completedDays($pairRuns, OrderingSource::DailyUsage);
            $stockDays = $this->completedDays($pairRuns, OrderingSource::StockMovement);
            $stockFreshAs = $pairRuns->where('source', OrderingSource::StockMovement)->where('status', OrderingSyncStatus::Completed)->max('finished_at');
            $pairMetrics = $metrics->filter(fn (OrderingDailyMetric $metric): bool => $metric->company_code === $pair->esb_comcode && $metric->esb_branch_code === $pair->esb_branch_code);

            $completeness['pairs'][] = [
                'company_code' => $pair->esb_comcode,
                'esb_branch_code' => $pair->esb_branch_code,
                'usage_valid_days' => count($usageDays),
                'usage_missing_days' => $windowDays - count($usageDays),
                'stock_valid_days' => count($stockDays),
                'stock_fresh_as_of' => $stockFreshAs?->toIso8601String(),
                'failed_runs' => $pairRuns->where('status', OrderingSyncStatus::Failed)->count(),
            ];

            foreach ($pairMetrics->groupBy(fn (OrderingDailyMetric $metric): string => $metric->product_code.'|'.$metric->uom) as $rows) {
                $item = $this->buildItem($branch, $pair, $rows, $pairMetrics, $usageDays, $stockDays, $stockFreshAs?->format('Y-m-d H:i:s'), $cutoff, $horizon, $config, $eligible, $windowDays);
                if ($item !== null) {
                    $items[] = $item;
                }
            }
        }

        return [$items, $completeness];
    }

    /**
     * @param  Collection<int, OrderingDailyMetric>  $rows
     * @param  Collection<int, OrderingDailyMetric>  $pairMetrics
     * @param  array<string, true>  $usageDays
     * @param  array<string, true>  $stockDays
     * @param  array<string, mixed>  $config
     * @param  list<string>|null  $eligible
     * @return array<string, mixed>|null
     */
    private function buildItem(Branch $branch, BranchEsbCode $pair, Collection $rows, Collection $pairMetrics, array $usageDays, array $stockDays, ?string $stockFreshAs, CarbonImmutable $cutoff, int $horizon, array $config, ?array $eligible, int $windowDays): ?array
    {
        $first = $rows->sortByDesc('metric_date')->first();
        // Only products with demand in the window are analysed; stock-only items are not ordered.
        if (! $rows->contains(fn (OrderingDailyMetric $metric): bool => (float) $metric->sales_driven_usage > 0)) {
            return null;
        }

        $category = $rows->pluck('category_name')->filter()->last();
        if ($eligible !== null && ($category === null || ! in_array(mb_strtolower($category), $eligible, true))) {
            return null;
        }

        $byDate = $rows->keyBy(fn (OrderingDailyMetric $metric): string => $metric->metric_date->toDateString());
        $history = [];
        foreach (array_keys($usageDays) as $date) {
            $metric = $byDate->get($date);
            $history[$date] = [
                'usage' => (float) ($metric?->sales_driven_usage ?? 0),
                'waste' => isset($stockDays[$date]) && $config['waste_transaction_types'] !== [] ? (float) ($metric?->waste_quantity ?? 0) : null,
            ];
        }

        $flags = [];
        if ($eligible === null) {
            $flags[] = 'category_unconfirmed';
        }
        if (count($usageDays) < $windowDays) {
            $flags[] = 'usage_partial';
        }

        $stock = ['quantity' => null, 'observed_at' => null, 'fresh_as_of' => $stockFreshAs, 'unavailable_reason' => null];
        if ($config['stock_location_scope'] === null) {
            $stock['unavailable_reason'] = 'stock_location_unconfirmed';
        } else {
            $latest = $rows->filter(fn (OrderingDailyMetric $metric): bool => $metric->ending_stock !== null)->sortByDesc('metric_date')->first();
            if ($latest !== null) {
                $stock['quantity'] = (float) $latest->ending_stock;
                $stock['observed_at'] = $latest->stock_observed_at?->format('Y-m-d H:i:s');
            } elseif ($pairMetrics->contains(fn (OrderingDailyMetric $metric): bool => $metric->product_code === $first->product_code && $metric->uom !== $first->uom && $metric->ending_stock !== null)) {
                // Stock exists for this product in another UOM; quantities are never converted implicitly.
                $flags[] = 'uom_conflict';
            }
        }

        $backtest = $this->backtest->evaluate($history, $cutoff->toDateString(), $config);
        $result = $this->calculator->calculate([
            'cutoff' => $cutoff->toDateString(),
            'horizon' => $horizon,
            'history' => $history,
            'stock' => $stock,
            'inbound' => null,
            'flags' => $flags,
            'method' => $backtest['method'],
            'backtest' => $backtest,
            'config' => $config,
        ]);

        return [
            'branch_id' => $branch->id,
            'company_code' => $pair->esb_comcode,
            'esb_branch_code' => $pair->esb_branch_code,
            'product_code' => $first->product_code,
            'product_detail_id' => $first->product_detail_id,
            'product_name' => $rows->pluck('product_name')->filter()->last(),
            'category_name' => $category,
            'uom' => $first->uom,
            ...collect($result)->except(['confidence', 'trend_label'])->map(fn ($value) => is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : $value)->all(),
            'confidence' => $result['confidence']->value,
            'trend_label' => $result['trend_label']->value,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * @param  Collection<int, OrderingSyncRun>  $runs
     * @return array<string, true>
     */
    private function completedDays(Collection $runs, OrderingSource $source): array
    {
        return $runs->where('source', $source)->where('status', OrderingSyncStatus::Completed)
            ->mapWithKeys(fn (OrderingSyncRun $run): array => [$run->period_from->toDateString() => true])
            ->all();
    }
}
