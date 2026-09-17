<?php

use App\Services\InboundGoodsReceiptQcService;

test('it calculates inbound quality gates and remaining shelf life', function () {
    $result = app(InboundGoodsReceiptQcService::class)->assessItem([
        'physicalQty' => 100, 'outstandingQty' => 100, 'tolerancePercentage' => 1,
        'colorResult' => 'pass', 'textureResult' => 'pass', 'packagingResult' => 'pass', 'contaminationResult' => 'pass',
        'temperatureCategory' => 'chilled', 'actualTemperature' => 4, 'minTemperature' => 2, 'maxTemperature' => 5,
        'shelfLifeRequired' => true, 'minimumShelfLifePercentage' => 80,
        'batches' => [['manufacturedDate' => '2026-01-01', 'expiredDate' => '2026-11-01']],
        'samplingRequired' => true, 'samplingResult' => 'pass',
    ], '2026-02-01');

    expect($result['quantityResult'])->toBe('pass')
        ->and($result['coldChainResult'])->toBe('pass')
        ->and($result['shelfLifeResult'])->toBe('pass')
        ->and($result['batches'][0]['shelfLifePercentage'])->toBeGreaterThan(80)
        ->and($result['canAccept'])->toBeTrue();
});

test('it blocks acceptance when cold chain or sampling fails', function () {
    $result = app(InboundGoodsReceiptQcService::class)->assessItem([
        'physicalQty' => 10, 'outstandingQty' => 10, 'tolerancePercentage' => 0,
        'colorResult' => 'pass', 'textureResult' => 'pass', 'packagingResult' => 'pass', 'contaminationResult' => 'pass',
        'temperatureCategory' => 'frozen', 'actualTemperature' => -8, 'minTemperature' => -20, 'maxTemperature' => -15,
        'shelfLifeRequired' => false, 'samplingRequired' => true, 'samplingResult' => 'pending',
    ], '2026-09-15');

    expect($result['coldChainResult'])->toBe('fail')
        ->and($result['samplingResult'])->toBe('pending')
        ->and($result['canAccept'])->toBeFalse();
});

test('it assigns higher vendor demerit for critical product failures', function () {
    $service = app(InboundGoodsReceiptQcService::class);

    expect($service->demeritPoints('cold_chain'))->toBe(20)
        ->and($service->demeritPoints('sampling'))->toBe(15)
        ->and($service->demeritPoints('quality'))->toBe(10)
        ->and($service->demeritPoints('other'))->toBe(5);
});

test('it recalculates shelf life instead of keeping a supplied percentage', function () {
    $result = app(InboundGoodsReceiptQcService::class)->assessItem([
        'shelfLifeRequired' => true,
        'batches' => [['manufacturedDate' => '2026-01-01', 'expiredDate' => '2026-01-11', 'shelfLifePercentage' => 100]],
    ], '2026-01-10');

    expect($result['batches'][0]['shelfLifePercentage'])->toBe(10.0)
        ->and($result['shelfLifeResult'])->toBe('fail')
        ->and($result['canAccept'])->toBeFalse();
});
