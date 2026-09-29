<?php

use App\Services\Rnd\Bom\BomChangeComparator;

function bomComparatorDetail(array $overrides = []): array
{
    return array_merge([
        'productDetailID' => 100,
        'productCode' => 'CRS',
        'productName' => 'Croissant',
        'uomName' => 'PCS',
        'is_active' => true,
        'bomDetails' => [
            ['productDetailID' => 200, 'productCode' => 'BTR', 'productName' => 'Butter', 'qty' => 100],
            ['productDetailID' => 201, 'productCode' => 'FLR', 'productName' => 'Flour', 'qty' => 50],
        ],
    ], $overrides);
}

it('reports no changes for two identical snapshots', function () {
    $detail = bomComparatorDetail();

    $diff = app(BomChangeComparator::class)->compare($detail, $detail);

    expect($diff['has_changes'])->toBeFalse()
        ->and($diff['components_added'])->toBeEmpty()
        ->and($diff['components_removed'])->toBeEmpty()
        ->and($diff['components_changed'])->toBeEmpty()
        ->and($diff['product_result_changed'])->toBeFalse()
        ->and($diff['unit_changed'])->toBeFalse()
        ->and($diff['status_changed'])->toBeFalse();
});

it('detects an added component', function () {
    $before = bomComparatorDetail();
    $after = bomComparatorDetail(['bomDetails' => array_merge($before['bomDetails'], [
        ['productDetailID' => 202, 'productCode' => 'SGR', 'productName' => 'Sugar', 'qty' => 20],
    ])]);

    $diff = app(BomChangeComparator::class)->compare($before, $after);

    expect($diff['has_changes'])->toBeTrue()
        ->and($diff['components_added'])->toHaveCount(1)
        ->and($diff['components_added'][0]['productCode'])->toBe('SGR')
        ->and($diff['components_removed'])->toBeEmpty()
        ->and($diff['components_changed'])->toBeEmpty();
});

it('detects a removed component', function () {
    $before = bomComparatorDetail();
    $after = bomComparatorDetail(['bomDetails' => [$before['bomDetails'][0]]]);

    $diff = app(BomChangeComparator::class)->compare($before, $after);

    expect($diff['components_added'])->toBeEmpty()
        ->and($diff['components_removed'])->toHaveCount(1)
        ->and($diff['components_removed'][0]['productCode'])->toBe('FLR');
});

it('detects a quantity change on an existing component', function () {
    $before = bomComparatorDetail();
    $after = bomComparatorDetail(['bomDetails' => [
        $before['bomDetails'][0],
        ['productDetailID' => 201, 'productCode' => 'FLR', 'productName' => 'Flour', 'qty' => 75],
    ]]);

    $diff = app(BomChangeComparator::class)->compare($before, $after);

    expect($diff['components_changed'])->toHaveCount(1)
        ->and($diff['components_changed'][0])->toMatchArray([
            'productDetailID' => 201,
            'before_qty' => 50.0,
            'after_qty' => 75.0,
        ]);
});

it('detects a Product Result change', function () {
    $before = bomComparatorDetail();
    $after = bomComparatorDetail(['productDetailID' => 999, 'productCode' => 'NEW', 'productName' => 'New Result']);

    $diff = app(BomChangeComparator::class)->compare($before, $after);

    expect($diff['product_result_changed'])->toBeTrue()
        ->and($diff['product_result']['before']['productDetailID'])->toBe(100)
        ->and($diff['product_result']['after']['productDetailID'])->toBe(999);
});

it('detects a unit change', function () {
    $before = bomComparatorDetail();
    $after = bomComparatorDetail(['uomName' => 'GRAM']);

    $diff = app(BomChangeComparator::class)->compare($before, $after);

    expect($diff['unit_changed'])->toBeTrue()
        ->and($diff['unit'])->toBe(['before' => 'PCS', 'after' => 'GRAM']);
});

it('detects a status change', function () {
    $before = bomComparatorDetail(['is_active' => true]);
    $after = bomComparatorDetail(['is_active' => false]);

    $diff = app(BomChangeComparator::class)->compare($before, $after);

    expect($diff['status_changed'])->toBeTrue()
        ->and($diff['status'])->toBe(['before' => true, 'after' => false]);
});

it('does not report a status change when status information is missing on either side', function () {
    $before = bomComparatorDetail();
    unset($before['is_active']);
    $after = bomComparatorDetail(['is_active' => false]);

    $diff = app(BomChangeComparator::class)->compare($before, $after);

    expect($diff['status_changed'])->toBeFalse();
});

it('ignores components without a valid productDetailID', function () {
    $before = bomComparatorDetail();
    $after = bomComparatorDetail(['bomDetails' => array_merge($before['bomDetails'], [
        ['productDetailID' => 0, 'productCode' => 'JUNK', 'productName' => 'Junk Row', 'qty' => 1],
    ])]);

    $diff = app(BomChangeComparator::class)->compare($before, $after);

    expect($diff['has_changes'])->toBeFalse();
});
