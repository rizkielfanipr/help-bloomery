<?php

use App\Services\Rnd\MenuPricingCalculator;

it('recommends a rounded selling price from the target food cost', function () {
    $result = app(MenuPricingCalculator::class)->fromTargetFoodCost(20_000, 30, 1_000);

    expect($result)
        ->recommended_price->toBe(67_000.0)
        ->food_cost_percentage->toBe(29.85)
        ->gross_margin->toBe(47_000.0)
        ->gross_margin_percentage->toBe(70.15);
});

it('applies an online adjustment and rounds the result up', function () {
    $result = app(MenuPricingCalculator::class)->withAdjustment(20_000, 67_000, 20, 1_000);

    expect($result)
        ->raw_price->toBe(80_400.0)
        ->recommended_price->toBe(81_000.0)
        ->food_cost_percentage->toBe(24.69);
});

it('does not calculate percentages without a valid hpp or selling price', function () {
    $calculator = app(MenuPricingCalculator::class);

    expect($calculator->foodCostPercentage(20_000, 0))->toBeNull()
        ->and($calculator->fromTargetFoodCost(20_000, 100, 1_000)['recommended_price'])->toBe(0.0);
});
