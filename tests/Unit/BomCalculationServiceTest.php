<?php

use App\Services\Rnd\BomCalculationService;

it('calculates component requirements with optional tolerance', function () {
    $calculator = app(BomCalculationService::class);
    $component = ['qty' => 100, 'tolerancePercent' => 10];

    expect($calculator->componentRequirement($component, 2))->toBe(200.0)
        ->and($calculator->componentRequirement($component, 2, true))->toEqualWithDelta(220.0, 0.000001);
});

it('converts a required WIP quantity into the proportional number of recipes', function () {
    $result = app(BomCalculationService::class)->childRecipeMultiplier(
        ['qty' => 130, 'conversionFactor' => 1],
        ['conversionFactor' => 6000],
        130,
    );

    expect($result['is_proportional'])->toBeTrue()
        ->and($result['multiplier'])->toBe(130 / 6000);
});

it('preserves the legacy multiplier when an output conversion is unavailable', function () {
    $result = app(BomCalculationService::class)->childRecipeMultiplier(['qty' => 2], [], 2);

    expect($result)->toBe(['multiplier' => 2.0, 'is_proportional' => false]);
});

it('uses the same component requirement for HPP line cost', function () {
    expect(app(BomCalculationService::class)->lineCost(['qty' => 25], 20))->toBe(500.0);
});
