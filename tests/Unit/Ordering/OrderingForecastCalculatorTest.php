<?php

use App\Enums\OrderingConfidence;
use App\Enums\OrderingTrendLabel;
use App\Services\Ordering\OrderingBacktestService;
use App\Services\Ordering\OrderingForecastCalculator;
use Carbon\CarbonImmutable;

function orderingConfig(array $overrides = []): array
{
    return array_replace_recursive([
        'recency_decay' => 0.85, 'min_weekday_observations' => 2,
        'trend_recent_days' => 14, 'trend_comparison_days' => 14, 'trend_cap' => ['lower' => 0.5, 'upper' => 2.0], 'trend_stable_band' => 0.05,
        'waste_rate_cap' => 0.30, 'safety_buffer_percent' => 0.10, 'stock_stale_hours' => 48,
        'confidence' => ['high_min_valid_days' => 42, 'medium_min_valid_days' => 21, 'high_max_wape' => 0.30, 'medium_max_wape' => 0.60],
        'priority_weights' => ['stockout' => 0.5, 'need' => 0.3, 'trend' => 0.2],
        'backtest' => ['holdout_days' => 14, 'min_samples' => 14],
    ], $overrides);
}

/** 56 days ending on the cutoff; $usage receives the day index (0 = oldest) and the date. */
function orderingHistory(Closure $usage, string $cutoff = '2026-10-18', int $days = 56, ?float $waste = 0.0): array
{
    $history = [];
    for ($i = 0; $i < $days; $i++) {
        $date = CarbonImmutable::parse($cutoff)->subDays($days - 1 - $i);
        $value = $usage($i, $date);
        if ($value !== null) {
            $history[$date->toDateString()] = ['usage' => (float) $value, 'waste' => $waste];
        }
    }

    return $history;
}

function orderingInput(array $history, array $overrides = []): array
{
    return array_replace([
        'cutoff' => '2026-10-18',
        'horizon' => 7,
        'history' => $history,
        'stock' => ['quantity' => 20.0, 'observed_at' => '2026-10-18 20:00:00', 'fresh_as_of' => '2026-10-19 04:00:00', 'unavailable_reason' => null],
        'inbound' => null,
        'flags' => [],
        'method' => OrderingForecastCalculator::METHOD_WEEKDAY_WEIGHTED,
        'backtest' => ['sample_count' => 14, 'mae' => 0.5, 'wape' => 0.05, 'bias' => 0.0, 'sufficient' => true],
        'config' => orderingConfig(),
    ], $overrides);
}

function calculateOrdering(array $input): array
{
    return (new OrderingForecastCalculator)->calculate($input);
}

it('weights same-weekday observations by recency and ignores missing days instead of counting them as zero', function () {
    // Mondays: 10 in older weeks, 20 in the most recent week; one Monday is missing entirely.
    $history = orderingHistory(function (int $i, CarbonImmutable $date): ?float {
        if (! $date->isMonday()) {
            return 5;
        }
        if ($date->toDateString() === '2026-09-28') {
            return null;
        }

        return $date->toDateString() === '2026-10-12' ? 20 : 10;
    });
    $calculator = new OrderingForecastCalculator;
    $baseline = $calculator->weekdayBaselines($history, CarbonImmutable::parse('2026-10-18'), OrderingForecastCalculator::METHOD_WEEKDAY_WEIGHTED, orderingConfig());

    $mondays = $baseline['explained'][1]['samples'];
    expect($mondays)->toHaveCount(7)
        ->and(collect($mondays)->pluck('date'))->not->toContain('2026-09-28')
        ->and(collect($mondays)->firstWhere('date', '2026-10-12')['weight'])->toBe(1.0)
        ->and($baseline['by_weekday'][1])->toBeGreaterThan(10.0)->toBeLessThan(20.0)
        ->and($baseline['by_weekday'][2])->toBe(5.0);
});

it('computes trend up, trend down, caps it, and never divides by a zero comparison window', function () {
    $calculator = new OrderingForecastCalculator;
    $cutoff = CarbonImmutable::parse('2026-10-18');
    $rising = orderingHistory(fn (int $i): float => $i >= 42 ? 12 : 10);
    $falling = orderingHistory(fn (int $i): float => $i >= 42 ? 8 : 10);
    $spike = orderingHistory(fn (int $i): float => $i >= 42 ? 100 : 10);
    $newProduct = orderingHistory(fn (int $i): float => $i >= 42 ? 5 : 0);
    $none = orderingHistory(fn (): float => 0);

    expect($calculator->trend($rising, $cutoff, orderingConfig()))->label->toBe(OrderingTrendLabel::Rising)->final->toBe(1.2)
        ->and($calculator->trend($falling, $cutoff, orderingConfig()))->label->toBe(OrderingTrendLabel::Falling)->final->toBe(0.8)
        ->and($calculator->trend($spike, $cutoff, orderingConfig()))->raw->toBe(10.0)->final->toBe(2.0)
        ->and($calculator->trend($newProduct, $cutoff, orderingConfig()))->label->toBe(OrderingTrendLabel::NewlySold)->raw->toBeNull()->final->toBe(1.0)
        ->and($calculator->trend($none, $cutoff, orderingConfig()))->label->toBe(OrderingTrendLabel::NoDemand);
});

it('builds the recommendation from forecast, waste, buffer, and stock with inbound left unavailable', function () {
    $result = calculateOrdering(orderingInput(orderingHistory(fn (): float => 10, waste: 1.0)));

    // Waste rate = 56 / (560 + 56) = 0.0909; forecast 70; buffer 7.
    expect($result['forecast_demand'])->toBe(70.0)
        ->and($result['waste_rate_raw'])->toBe(0.0909)
        ->and($result['expected_waste'])->toBe(round(70 * (1 / 11), 4))
        ->and($result['safety_buffer'])->toBe(7.0)
        ->and($result['gross_requirement'])->toBe(round(70 + 70 / 11 + 7, 4))
        ->and($result['raw_recommendation'])->toBe(round(70 + 70 / 11 + 7 - 20, 4))
        ->and($result['confirmed_inbound'])->toBeNull()
        ->and($result['reason_codes'])->toContain('inbound_not_available')
        ->and($result['explanation'])->toContain('Inbound belum diperhitungkan')
        ->and($result['coverage_days'])->toBe(2.0)
        ->and($result['predicted_stockout_date'])->toBe('2026-10-20')
        ->and($result['confidence'])->toBe(OrderingConfidence::Medium);
});

it('subtracts a known inbound and never recommends a negative quantity', function () {
    $withInbound = calculateOrdering(orderingInput(orderingHistory(fn (): float => 10), ['inbound' => 30.0]));
    $overstock = calculateOrdering(orderingInput(orderingHistory(fn (): float => 10), ['stock' => ['quantity' => 500.0, 'observed_at' => null, 'fresh_as_of' => '2026-10-19 04:00:00', 'unavailable_reason' => null]]));

    expect($withInbound['raw_recommendation'])->toBe(round(77 - 20 - 30, 4))
        ->and($withInbound['reason_codes'])->not->toContain('inbound_not_available')
        ->and($overstock['raw_recommendation'])->toBeLessThan(0.0)
        ->and($overstock['final_recommendation'])->toBe(0.0)
        ->and($overstock['predicted_stockout_date'])->toBeNull();
});

it('reports waste as not available when its denominator is zero and caps an abnormal waste rate', function () {
    $calculator = new OrderingForecastCalculator;
    expect($calculator->waste(orderingHistory(fn (): float => 0, waste: 0.0), orderingConfig()))->raw->toBeNull()->capped->toBeNull()
        ->and($calculator->waste(orderingHistory(fn (): float => 10, waste: null), orderingConfig()))->total->toBeNull()
        ->and($calculator->waste(orderingHistory(fn (): float => 1, waste: 9.0), orderingConfig()))->raw->toBe(0.9)->capped->toBe(0.3);
});

it('blocks the recommendation without demand, stock, or fresh stock but keeps the demand it can explain', function (array $override, string $reason) {
    $result = calculateOrdering(orderingInput(orderingHistory(fn (): float => 10), $override));

    expect($result['confidence'])->toBe(OrderingConfidence::Blocked)
        ->and($result['reason_codes'])->toContain($reason)
        ->and($result['final_recommendation'])->toBeNull()
        ->and($result['explanation'])->toStartWith('Tidak dapat dihitung');
})->with([
    'no stock location' => [['stock' => ['quantity' => null, 'observed_at' => null, 'fresh_as_of' => null, 'unavailable_reason' => 'stock_location_unconfirmed']], 'stock_location_unconfirmed'],
    'stock missing' => [['stock' => ['quantity' => null, 'observed_at' => null, 'fresh_as_of' => null, 'unavailable_reason' => null]], 'stock_not_available'],
    'stale stock' => [['stock' => ['quantity' => 5.0, 'observed_at' => '2026-10-10 10:00:00', 'fresh_as_of' => '2026-10-10 10:00:00', 'unavailable_reason' => null]], 'stock_stale'],
    'uom conflict' => [['flags' => ['uom_conflict']], 'uom_conflict'],
    'no demand data' => [['history' => []], 'demand_not_available'],
]);

it('flags negative stock as an anomaly with Low confidence and marks the stockout as already happened', function () {
    $result = calculateOrdering(orderingInput(orderingHistory(fn (): float => 10), ['stock' => ['quantity' => -4.0, 'observed_at' => null, 'fresh_as_of' => '2026-10-19 04:00:00', 'unavailable_reason' => null]]));

    expect($result['reason_codes'])->toContain('negative_stock')
        ->and($result['confidence'])->toBe(OrderingConfidence::Low)
        ->and($result['coverage_days'])->toBe(0.0)
        ->and($result['predicted_stockout_date'])->toBe('2026-10-19');
});

it('assigns High, Medium, and Low confidence from history, inbound, and backtest error', function () {
    $history = orderingHistory(fn (): float => 10);

    expect(calculateOrdering(orderingInput($history, ['inbound' => 0.0]))['confidence'])->toBe(OrderingConfidence::High)
        ->and(calculateOrdering(orderingInput($history))['confidence'])->toBe(OrderingConfidence::Medium)
        ->and(calculateOrdering(orderingInput(orderingHistory(fn (): float => 10, days: 30), ['inbound' => 0.0]))['confidence'])->toBe(OrderingConfidence::Medium)
        ->and(calculateOrdering(orderingInput(orderingHistory(fn (): float => 10, days: 14), ['inbound' => 0.0]))['confidence'])->toBe(OrderingConfidence::Low)
        ->and(calculateOrdering(orderingInput($history, ['inbound' => 0.0, 'backtest' => ['sample_count' => 14, 'mae' => 5, 'wape' => 0.9, 'bias' => 0.1, 'sufficient' => true]]))['confidence'])->toBe(OrderingConfidence::Low)
        ->and(calculateOrdering(orderingInput($history, ['inbound' => 0.0, 'flags' => ['category_unconfirmed']]))['confidence'])->toBe(OrderingConfidence::Low)
        ->and(calculateOrdering(orderingInput($history, ['inbound' => 0.0, 'backtest' => null]))['confidence'])->toBe(OrderingConfidence::Low);
});

it('labels a period without demand instead of reporting infinite coverage', function () {
    $result = calculateOrdering(orderingInput(orderingHistory(fn (): float => 0)));

    expect($result['coverage_days'])->toBeNull()
        ->and($result['reason_codes'])->toContain('no_demand_in_period')
        ->and($result['final_recommendation'])->toBe(0.0)
        ->and($result['trend_label'])->toBe(OrderingTrendLabel::NoDemand);
});

it('returns the same result for the same input and version', function () {
    $input = orderingInput(orderingHistory(fn (int $i): float => 5 + ($i % 7)));

    expect(calculateOrdering($input))->toBe(calculateOrdering($input));
});

it('computes MAE, WAPE, and Bias and leaves WAPE and Bias unavailable for a zero actual total', function () {
    $backtest = new OrderingBacktestService(new OrderingForecastCalculator);

    expect($backtest->metrics([['forecast' => 12, 'actual' => 10], ['forecast' => 8, 'actual' => 10]]))->toBe(['mae' => 2.0, 'wape' => 0.2, 'bias' => 0.0])
        ->and($backtest->metrics([['forecast' => 3, 'actual' => 0]]))->toBe(['mae' => 3.0, 'wape' => null, 'bias' => null])
        ->and($backtest->metrics([]))->toBe(['mae' => null, 'wape' => null, 'bias' => null]);
});

it('selects the most accurate candidate only with enough samples and falls back to the default otherwise', function () {
    $backtest = new OrderingBacktestService(new OrderingForecastCalculator);
    // A strict weekly pattern is matched exactly by the same-weekday methods.
    $weekly = orderingHistory(fn (int $i, CarbonImmutable $date): float => $date->isSaturday() ? 40 : 5);

    $result = $backtest->evaluate($weekly, '2026-10-18', orderingConfig());
    expect($result['sufficient'])->toBeTrue()
        ->and($result['sample_count'])->toBe(14)
        ->and($result['wape'])->toBe(0.0)
        ->and(array_keys($result['candidates']))->toBe(OrderingForecastCalculator::METHODS);

    $short = $backtest->evaluate(orderingHistory(fn (): float => 5, days: 10), '2026-10-18', orderingConfig());
    expect($short['sufficient'])->toBeFalse()
        ->and($short['method'])->toBe(OrderingForecastCalculator::METHOD_WEEKDAY_WEIGHTED);
});
