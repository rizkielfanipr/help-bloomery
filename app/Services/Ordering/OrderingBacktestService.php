<?php

namespace App\Services\Ordering;

use Carbon\CarbonImmutable;

/**
 * Compares the candidate methods on days whose actual demand is already known
 * (docs/ordering-recommendation-prd.md §14): each holdout day is forecast only from the history
 * before it. The best method is picked only when the sample is large enough; otherwise the
 * default method is kept and the item's confidence stays Low.
 */
class OrderingBacktestService
{
    public function __construct(private readonly OrderingForecastCalculator $calculator) {}

    /**
     * @param  array<string, array{usage: float, waste: ?float}>  $history  valid days only
     * @param  array<string, mixed>  $config
     * @return array{method: string, sample_count: int, mae: ?float, wape: ?float, bias: ?float, sufficient: bool, candidates: array<string, array{mae: ?float, wape: ?float, bias: ?float}>}
     */
    public function evaluate(array $history, string $cutoff, array $config): array
    {
        ksort($history);
        $holdoutFrom = CarbonImmutable::parse($cutoff)->subDays((int) $config['backtest']['holdout_days'] - 1)->toDateString();
        $holdout = array_filter($history, fn (string $date): bool => $date >= $holdoutFrom, ARRAY_FILTER_USE_KEY);
        $candidates = [];

        foreach (OrderingForecastCalculator::METHODS as $method) {
            $pairs = [];
            foreach ($holdout as $date => $observation) {
                $target = CarbonImmutable::parse($date);
                $training = array_filter($history, fn (string $day): bool => $day < $date, ARRAY_FILTER_USE_KEY);
                if ($training === []) {
                    continue;
                }
                $trainingCutoff = $target->subDay();
                $trend = $this->calculator->trend($training, $trainingCutoff, $config);
                $baseline = $this->calculator->weekdayBaselines($training, $trainingCutoff, $method, $config)['by_weekday'][$target->dayOfWeekIso];
                $pairs[] = ['forecast' => $baseline * $trend['final'], 'actual' => $observation['usage']];
            }
            $candidates[$method] = ['count' => count($pairs), ...$this->metrics($pairs)];
        }

        $sampleCount = $candidates[OrderingForecastCalculator::METHOD_WEEKDAY_WEIGHTED]['count'];
        $sufficient = $sampleCount >= (int) $config['backtest']['min_samples'];
        $selected = $sufficient ? $this->best($candidates) : OrderingForecastCalculator::METHOD_WEEKDAY_WEIGHTED;

        return [
            'method' => $selected,
            'sample_count' => $sampleCount,
            'mae' => $candidates[$selected]['mae'],
            'wape' => $candidates[$selected]['wape'],
            'bias' => $candidates[$selected]['bias'],
            'sufficient' => $sufficient,
            'candidates' => collect($candidates)->map(fn (array $candidate): array => ['mae' => $candidate['mae'], 'wape' => $candidate['wape'], 'bias' => $candidate['bias']])->all(),
        ];
    }

    /**
     * MAE = mean |F − A|; WAPE = Σ|F − A| ÷ ΣA; Bias = Σ(F − A) ÷ ΣA. A zero actual total makes
     * WAPE and Bias unavailable instead of dividing by zero.
     *
     * @param  list<array{forecast: float, actual: float}>  $pairs
     * @return array{mae: ?float, wape: ?float, bias: ?float}
     */
    public function metrics(array $pairs): array
    {
        if ($pairs === []) {
            return ['mae' => null, 'wape' => null, 'bias' => null];
        }

        $absolute = array_sum(array_map(fn (array $pair): float => abs($pair['forecast'] - $pair['actual']), $pairs));
        $signed = array_sum(array_map(fn (array $pair): float => $pair['forecast'] - $pair['actual'], $pairs));
        $actual = array_sum(array_column($pairs, 'actual'));

        return [
            'mae' => round($absolute / count($pairs), 4),
            'wape' => $actual > 0 ? round($absolute / $actual, 4) : null,
            'bias' => $actual > 0 ? round($signed / $actual, 4) : null,
        ];
    }

    /**
     * Lowest WAPE (MAE when WAPE is unavailable); ties keep the default method order.
     *
     * @param  array<string, array{count: int, mae: ?float, wape: ?float, bias: ?float}>  $candidates
     */
    private function best(array $candidates): string
    {
        $key = collect($candidates)->contains(fn (array $candidate): bool => $candidate['wape'] !== null) ? 'wape' : 'mae';
        $best = OrderingForecastCalculator::METHOD_WEEKDAY_WEIGHTED;
        foreach (OrderingForecastCalculator::METHODS as $method) {
            $value = $candidates[$method][$key];
            if ($value !== null && ($candidates[$best][$key] === null || $value < $candidates[$best][$key])) {
                $best = $method;
            }
        }

        return $best;
    }
}
