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

test('it labels and colors the remaining-days readout for a future, today, and past expiry', function () {
    $service = app(InboundGoodsReceiptQcService::class);

    $future = $service->expiryStatus('2026-12-30', '2026-10-01');
    expect($future['days'])->toBe(90)
        ->and($future['label'])->toBe('90 hari lagi')
        ->and($future['color'])->toBe('green');

    $today = $service->expiryStatus('2026-10-01', '2026-10-01');
    expect($today['days'])->toBe(0)
        ->and($today['label'])->toBe('Hari ini')
        ->and($today['color'])->toBe('amber');

    $past = $service->expiryStatus('2026-09-28', '2026-10-01');
    expect($past['days'])->toBe(-3)
        ->and($past['label'])->toBe('Lewat 3 hari')
        ->and($past['color'])->toBe('red');
});

test('it treats an expiry within the near-expiry window as amber, not green', function () {
    $result = app(InboundGoodsReceiptQcService::class)->expiryStatus('2026-10-08', '2026-10-01');

    expect($result['days'])->toBe(7)
        ->and($result['color'])->toBe('amber');
});
