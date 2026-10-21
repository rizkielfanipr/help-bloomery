<?php

namespace App\Services\Ordering;

use App\Enums\OrderingConfidence;
use App\Enums\OrderingTrendLabel;
use Carbon\CarbonImmutable;

/**
 * Deterministic forecast and recommendation for one branch-product-UOM
 * (docs/ordering-recommendation-prd.md §13). Pure PHP: no Filament, Livewire, HTTP, or database,
 * so the same input and configuration always produce the same output.
 *
 * History contains only valid observation days — a day whose demand source failed or was not
 * read is absent, never zero. Every component of the formula is returned so the detail view and
 * export can explain the result without recomputing it.
 */
class OrderingForecastCalculator
{
    public const METHOD_WEEKDAY_WEIGHTED = 'weekday_weighted';

    public const METHOD_SEASONAL_NAIVE = 'seasonal_naive';

    public const METHOD_ROLLING_WEEKDAY = 'rolling_weekday_factor';

    public const METHOD_WEEKDAY_MEDIAN = 'weekday_median';

    /** @var list<string> */
    public const METHODS = [self::METHOD_WEEKDAY_WEIGHTED, self::METHOD_SEASONAL_NAIVE, self::METHOD_ROLLING_WEEKDAY, self::METHOD_WEEKDAY_MEDIAN];

    /**
     * @param  array{
     *     cutoff: string,
     *     horizon: int,
     *     history: array<string, array{usage: float, waste: ?float}>,
     *     stock: array{quantity: ?float, observed_at: ?string, fresh_as_of: ?string, unavailable_reason: ?string},
     *     inbound: ?float,
     *     flags: list<string>,
     *     method: string,
     *     backtest: ?array{sample_count: int, mae: ?float, wape: ?float, bias: ?float, sufficient: bool},
     *     config: array<string, mixed>
     * }  $input
     * @return array<string, mixed>
     */
    public function calculate(array $input): array
    {
        $config = $input['config'];
        $cutoff = CarbonImmutable::parse($input['cutoff'])->startOfDay();
        $history = $this->sortedHistory($input['history']);
        $reasons = $input['flags'];
        $confidence = OrderingConfidence::High;

        $validDays = count($history);
        $historicalDemand = $validDays > 0 ? array_sum(array_column($history, 'usage')) : null;
        $averageDaily = $validDays > 0 ? $historicalDemand / $validDays : null;

        $trend = $this->trend($history, $cutoff, $config);
        $baselines = $this->weekdayBaselines($history, $cutoff, $input['method'], $config);
        if ($baselines['sparse']) {
            $reasons[] = 'sparse_weekday_history';
        }

        $daily = [];
        $forecastDemand = null;
        if ($validDays > 0) {
            $forecastDemand = 0.0;
            for ($day = 1; $day <= $input['horizon']; $day++) {
                $date = $cutoff->addDays($day);
                $baseline = $baselines['by_weekday'][$date->dayOfWeekIso];
                $value = $baseline * $trend['final'];
                $forecastDemand += $value;
                $daily[] = ['date' => $date->toDateString(), 'baseline' => round($baseline, 4), 'forecast' => round($value, 4)];
            }
        }

        $waste = $this->waste($history, $config);
        $expectedWaste = $forecastDemand !== null && $waste['capped'] !== null ? $forecastDemand * $waste['capped'] : null;
        if ($waste['capped'] === null) {
            $reasons[] = 'waste_not_available';
        }

        $bufferPercent = (float) $config['safety_buffer_percent'];
        $safetyBuffer = $forecastDemand !== null ? $forecastDemand * $bufferPercent : null;

        $stock = $input['stock'];
        $stockQuantity = $stock['quantity'];
        if ($stockQuantity === null) {
            $reasons[] = $stock['unavailable_reason'] ?? 'stock_not_available';
        } elseif ($this->isStale($stock['fresh_as_of'], $cutoff, (int) $config['stock_stale_hours'])) {
            $reasons[] = 'stock_stale';
        } elseif ($stockQuantity < 0) {
            $reasons[] = 'negative_stock';
        }

        $inbound = $input['inbound'];
        if ($inbound === null) {
            $reasons[] = 'inbound_not_available';
        }

        if ($validDays === 0) {
            $reasons[] = 'demand_not_available';
        } elseif ($averageDaily == 0.0) {
            $reasons[] = 'no_demand_in_period';
        }

        $blocked = array_intersect($reasons, ['demand_not_available', 'stock_not_available', 'stock_location_unconfirmed', 'stock_stale', 'uom_conflict', 'identity_invalid', 'branch_mismatch']) !== [];

        $gross = $raw = $final = $coverage = null;
        $stockoutDate = null;
        if (! $blocked) {
            $gross = $forecastDemand + ($expectedWaste ?? 0.0) + $safetyBuffer;
            $raw = $gross - $stockQuantity - ($inbound ?? 0.0);
            $final = max(0.0, $raw);
            $coverage = $averageDaily > 0 ? max(0.0, $stockQuantity) / $averageDaily : null;
            [$daily, $stockoutDate] = $this->project($daily, $stockQuantity, $cutoff);
        }

        $confidence = $this->confidence($reasons, $validDays, $trend['label'], $input['backtest'], $blocked, $config);
        $priority = $blocked ? null : $this->priority($final, $forecastDemand, $stockoutDate, $cutoff, $input['horizon'], $trend['final'], $config);
        $reasons = array_values(array_unique($reasons));

        return [
            'valid_days' => $validDays,
            'historical_demand' => $this->round($historicalDemand),
            'average_daily_demand' => $this->round($averageDaily),
            'weekday_baseline' => $baselines['explained'],
            'trend_raw' => $this->round($trend['raw']),
            'trend_final' => $this->round($trend['final']),
            'trend_label' => $trend['label'],
            'historical_waste' => $this->round($waste['total']),
            'waste_rate_raw' => $this->round($waste['raw']),
            'waste_rate_capped' => $this->round($waste['capped']),
            'forecast_demand' => $this->round($forecastDemand),
            'expected_waste' => $this->round($expectedWaste),
            'buffer_percent' => $bufferPercent,
            'safety_buffer' => $this->round($safetyBuffer),
            'available_stock' => $this->round($stockQuantity),
            'stock_observed_at' => $stock['observed_at'],
            'confirmed_inbound' => $this->round($inbound),
            'gross_requirement' => $this->round($gross),
            'raw_recommendation' => $this->round($raw),
            'final_recommendation' => $this->round($final),
            'coverage_days' => $coverage !== null ? round($coverage, 2) : null,
            'predicted_stockout_date' => $stockoutDate,
            'confidence' => $confidence,
            'reason_codes' => $reasons,
            'daily_breakdown' => $daily,
            'selected_method' => $input['method'],
            'backtest' => $input['backtest'],
            'priority_score' => $priority !== null ? round($priority, 4) : null,
            'explanation' => $this->explain($confidence, $reasons, $forecastDemand, $trend, $waste['capped'], $stockQuantity, $stockoutDate, $final, $input['horizon']),
        ];
    }

    /**
     * Baseline per ISO weekday for one candidate method, from history before the cutoff only.
     *
     * @param  array<string, array{usage: float, waste: ?float}>  $history
     * @param  array<string, mixed>  $config
     * @return array{by_weekday: array<int, float>, sparse: bool, explained: array<int, array<string, mixed>>}
     */
    public function weekdayBaselines(array $history, CarbonImmutable $cutoff, string $method, array $config): array
    {
        $overall = $history !== [] ? array_sum(array_column($history, 'usage')) / count($history) : 0.0;
        $byWeekday = [];
        $explained = [];
        $sparse = false;

        for ($weekday = 1; $weekday <= 7; $weekday++) {
            $samples = [];
            foreach ($history as $date => $observation) {
                $day = CarbonImmutable::parse($date);
                if ($day->dayOfWeekIso === $weekday) {
                    $weeksBack = max(1, (int) ceil(($cutoff->diffInDays($day, true) + 1) / 7));
                    $samples[] = ['date' => $date, 'usage' => $observation['usage'], 'weight' => round((float) $config['recency_decay'] ** ($weeksBack - 1), 6)];
                }
            }

            if (count($samples) < (int) $config['min_weekday_observations']) {
                $sparse = $sparse || $samples !== [];
                $value = $method === self::METHOD_SEASONAL_NAIVE && $samples !== [] ? end($samples)['usage'] : $overall;
                $byWeekday[$weekday] = (float) $value;
                $explained[$weekday] = ['samples' => $samples, 'value' => round((float) $value, 4), 'fallback' => 'overall_average'];

                continue;
            }

            $value = match ($method) {
                self::METHOD_SEASONAL_NAIVE => end($samples)['usage'],
                self::METHOD_WEEKDAY_MEDIAN => $this->median(array_column($samples, 'usage')),
                self::METHOD_ROLLING_WEEKDAY => $this->rollingWeekday($history, $samples, $overall),
                default => array_sum(array_map(fn (array $sample): float => $sample['usage'] * $sample['weight'], $samples)) / array_sum(array_column($samples, 'weight')),
            };
            $byWeekday[$weekday] = (float) $value;
            $explained[$weekday] = ['samples' => $samples, 'value' => round((float) $value, 4), 'fallback' => null];
        }

        return ['by_weekday' => $byWeekday, 'sparse' => $sparse, 'explained' => $explained];
    }

    /**
     * @param  array<string, array{usage: float, waste: ?float}>  $history
     * @param  array<string, mixed>  $config
     * @return array{raw: ?float, final: float, label: OrderingTrendLabel, recent_average: ?float, comparison_average: ?float}
     */
    public function trend(array $history, CarbonImmutable $cutoff, array $config): array
    {
        $recentFrom = $cutoff->subDays((int) $config['trend_recent_days'] - 1)->toDateString();
        $comparisonFrom = $cutoff->subDays((int) $config['trend_recent_days'] + (int) $config['trend_comparison_days'] - 1)->toDateString();
        $recent = $comparison = [];
        foreach ($history as $date => $observation) {
            if ($date >= $recentFrom) {
                $recent[] = $observation['usage'];
            } elseif ($date >= $comparisonFrom) {
                $comparison[] = $observation['usage'];
            }
        }

        if ($recent === [] || $comparison === []) {
            return ['raw' => null, 'final' => 1.0, 'label' => OrderingTrendLabel::Unknown, 'recent_average' => $recent === [] ? null : array_sum($recent) / count($recent), 'comparison_average' => $comparison === [] ? null : array_sum($comparison) / count($comparison)];
        }

        $recentAverage = array_sum($recent) / count($recent);
        $comparisonAverage = array_sum($comparison) / count($comparison);
        if ($comparisonAverage == 0.0) {
            return ['raw' => null, 'final' => 1.0, 'label' => $recentAverage == 0.0 ? OrderingTrendLabel::NoDemand : OrderingTrendLabel::NewlySold, 'recent_average' => $recentAverage, 'comparison_average' => 0.0];
        }

        $raw = $recentAverage / $comparisonAverage;
        $final = min((float) $config['trend_cap']['upper'], max((float) $config['trend_cap']['lower'], $raw));
        $band = (float) $config['trend_stable_band'];
        $label = match (true) {
            $final > 1 + $band => OrderingTrendLabel::Rising,
            $final < 1 - $band => OrderingTrendLabel::Falling,
            default => OrderingTrendLabel::Stable,
        };

        return ['raw' => $raw, 'final' => $final, 'label' => $label, 'recent_average' => $recentAverage, 'comparison_average' => $comparisonAverage];
    }

    /**
     * @param  array<string, array{usage: float, waste: ?float}>  $history
     * @param  array<string, mixed>  $config
     * @return array{total: ?float, raw: ?float, capped: ?float}
     */
    public function waste(array $history, array $config): array
    {
        $waste = $usage = 0.0;
        $known = false;
        foreach ($history as $observation) {
            if ($observation['waste'] === null) {
                continue;
            }
            $known = true;
            $waste += $observation['waste'];
            $usage += $observation['usage'];
        }

        if (! $known || ($usage + $waste) == 0.0) {
            return ['total' => $known ? $waste : null, 'raw' => null, 'capped' => null];
        }

        $raw = $waste / ($usage + $waste);

        return ['total' => $waste, 'raw' => $raw, 'capped' => min($raw, (float) $config['waste_rate_cap'])];
    }

    /**
     * @param  array<string, array{usage: float, waste: ?float}>  $history
     * @return array<string, array{usage: float, waste: ?float}>
     */
    private function sortedHistory(array $history): array
    {
        ksort($history);

        return $history;
    }

    /**
     * @param  list<array<string, mixed>>  $daily
     * @return array{0: list<array<string, mixed>>, 1: ?string}
     */
    private function project(array $daily, float $stock, CarbonImmutable $cutoff): array
    {
        $cumulative = 0.0;
        $stockoutDate = $stock <= 0 ? $cutoff->addDay()->toDateString() : null;
        foreach ($daily as $index => $day) {
            $cumulative += $day['forecast'];
            $remaining = $stock - $cumulative;
            $daily[$index]['cumulative'] = round($cumulative, 4);
            $daily[$index]['projected_stock'] = round($remaining, 4);
            if ($stockoutDate === null && $remaining <= 0 && $day['forecast'] > 0) {
                $stockoutDate = $day['date'];
            }
        }

        return [$daily, $stockoutDate];
    }

    /**
     * @param  list<string>  $reasons
     * @param  array<string, mixed>|null  $backtest
     * @param  array<string, mixed>  $config
     */
    private function confidence(array $reasons, int $validDays, OrderingTrendLabel $trend, ?array $backtest, bool $blocked, array $config): OrderingConfidence
    {
        if ($blocked) {
            return OrderingConfidence::Blocked;
        }

        $thresholds = $config['confidence'];
        $caps = [OrderingConfidence::High->rank()];
        $caps[] = match (true) {
            $validDays < (int) $thresholds['medium_min_valid_days'] => OrderingConfidence::Low->rank(),
            $validDays < (int) $thresholds['high_min_valid_days'] => OrderingConfidence::Medium->rank(),
            default => OrderingConfidence::High->rank(),
        };

        $low = ['sparse_weekday_history', 'negative_stock', 'category_unconfirmed', 'usage_partial', 'no_demand_in_period'];
        $medium = ['inbound_not_available', 'waste_not_available', 'stock_partial'];
        foreach ($reasons as $reason) {
            if (in_array($reason, $low, true)) {
                $caps[] = OrderingConfidence::Low->rank();
            } elseif (in_array($reason, $medium, true)) {
                $caps[] = OrderingConfidence::Medium->rank();
            }
        }

        if (in_array($trend, [OrderingTrendLabel::NewlySold, OrderingTrendLabel::Unknown], true)) {
            $caps[] = OrderingConfidence::Low->rank();
        }

        if ($backtest === null || ! $backtest['sufficient']) {
            $caps[] = OrderingConfidence::Low->rank();
        } elseif ($backtest['wape'] !== null) {
            $caps[] = match (true) {
                $backtest['wape'] > (float) $thresholds['medium_max_wape'] => OrderingConfidence::Low->rank(),
                $backtest['wape'] > (float) $thresholds['high_max_wape'] => OrderingConfidence::Medium->rank(),
                default => OrderingConfidence::High->rank(),
            };
        }

        return OrderingConfidence::fromRank(min($caps));
    }

    /**
     * 0–1 urgency: how soon stock runs out, how much of the forecast still has to be ordered, and
     * how strongly demand rises — weights from config, explained in the UI next to the score.
     *
     * @param  array<string, mixed>  $config
     */
    private function priority(?float $final, ?float $forecast, ?string $stockoutDate, CarbonImmutable $cutoff, int $horizon, float $trend, array $config): ?float
    {
        if ($final === null || $forecast === null) {
            return null;
        }

        $weights = $config['priority_weights'];
        $stockout = $stockoutDate === null ? 0.0 : max(0.0, min(1.0, 1 - ($cutoff->diffInDays(CarbonImmutable::parse($stockoutDate)) - 1) / $horizon));
        $need = $forecast > 0 ? min(1.0, $final / $forecast) : 0.0;
        $rise = max(0.0, min(1.0, ($trend - 1) / max(0.0001, (float) $config['trend_cap']['upper'] - 1)));

        return $weights['stockout'] * $stockout + $weights['need'] * $need + $weights['trend'] * $rise;
    }

    /**
     * Operational explanation built only from stored components.
     *
     * @param  list<string>  $reasons
     * @param  array<string, mixed>  $trend
     */
    private function explain(OrderingConfidence $confidence, array $reasons, ?float $forecast, array $trend, ?float $wasteRate, ?float $stock, ?string $stockoutDate, ?float $final, int $horizon): string
    {
        if ($confidence === OrderingConfidence::Blocked) {
            return 'Tidak dapat dihitung: '.implode('; ', array_map(fn (string $reason): string => OrderingReasonCodes::label($reason), array_values(array_intersect($reasons, OrderingReasonCodes::BLOCKING)))).'.';
        }

        $parts = [sprintf('Perkiraan kebutuhan %d hari %s', $horizon, $this->format($forecast))];
        $parts[] = match ($trend['label']) {
            OrderingTrendLabel::Rising, OrderingTrendLabel::Falling, OrderingTrendLabel::Stable => sprintf('tren %s%s%%', $trend['final'] >= 1 ? '+' : '', $this->format(($trend['final'] - 1) * 100, 1)),
            default => mb_strtolower($trend['label']->getLabel()),
        };
        if ($wasteRate !== null) {
            $parts[] = sprintf('waste %s%%', $this->format($wasteRate * 100, 1));
        }
        $parts[] = sprintf('stok %s', $this->format($stock));
        if ($stockoutDate !== null) {
            $parts[] = 'stok diperkirakan habis '.CarbonImmutable::parse($stockoutDate)->isoFormat('D MMM');
        }
        $sentence = ucfirst(implode(', ', $parts)).'. Rekomendasi order '.$this->format($final).'.';
        if (in_array('inbound_not_available', $reasons, true)) {
            $sentence .= ' Inbound belum diperhitungkan.';
        }

        return $sentence;
    }

    /**
     * @param  array<string, array{usage: float, waste: ?float}>  $history
     * @param  list<array{date: string, usage: float, weight: float}>  $samples
     */
    private function rollingWeekday(array $history, array $samples, float $overall): float
    {
        $recent = array_slice(array_column($history, 'usage'), -28);
        $rolling = $recent === [] ? 0.0 : array_sum($recent) / count($recent);
        $weekdayAverage = array_sum(array_column($samples, 'usage')) / count($samples);

        return $overall > 0 ? $rolling * ($weekdayAverage / $overall) : $rolling;
    }

    /** @param list<float> $values */
    private function median(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        return $count % 2 === 1 ? (float) $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
    }

    private function isStale(?string $freshAsOf, CarbonImmutable $cutoff, int $hours): bool
    {
        return $freshAsOf === null || CarbonImmutable::parse($freshAsOf)->lt($cutoff->endOfDay()->subHours($hours));
    }

    private function round(?float $value): ?float
    {
        return $value === null ? null : round($value, 4);
    }

    private function format(?float $value, int $decimals = 2): string
    {
        return $value === null ? '-' : rtrim(rtrim(number_format($value, $decimals, ',', '.'), '0'), ',');
    }
}
