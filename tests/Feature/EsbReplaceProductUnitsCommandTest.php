<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

function replaceUnitsWorkbook(): string
{
    $path = tempnam(sys_get_temp_dir(), 'replace').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['Product Code', 'Product Name', 'Base Unit', 'Unit Asal', 'Conversion Factor', 'Unit UOM', 'SKU New']));
    $writer->addRow(Row::fromValues(['BW1', 'x', 'GR', 'Resep', 150, 'Resep@150GR', 'BW1-Resep@150GR']));
    $writer->close();

    return $path;
}

function replaceUnitsProduct(bool $withNewUnit = false, ?int $menuId = null): array
{
    $detail = fn (int $id, int $uom, string $name, float $qty, bool $base, bool $flags, ?int $menu = null) => [
        'productDetailID' => $id, 'uomID' => $uom, 'uomName' => $name, 'qty' => $qty, 'basePrice' => 10, 'SKU' => "S{$id}",
        'cubication' => 0, 'weight' => 0, 'isBase' => $base, 'isStock' => $flags, 'isPurchase' => $flags,
        'isTransfer' => $flags, 'isSales' => $flags, 'menuID' => $menu, 'flagActive' => true,
    ];

    $details = [$detail(1, 5, 'GR', 1, true, false), $detail(2, 16, 'Resep', 150, false, true, $menuId)];
    if ($withNewUnit) {
        $details[] = $detail(3, 90, 'Resep@150GR', 150, false, true);
    }

    return ['status' => 'ok', 'result' => [
        'categoryID' => 46, 'subCategoryID' => 92, 'productName' => 'WIP | X', 'productCode' => 'BW1',
        'bomID' => null, 'requestable' => true, 'purchasable' => true, 'saleable' => true, 'VAT' => false,
        'receiptTolerance' => 0, 'notes' => '', 'coretaxProductCodeID' => null, 'flagLuxuryItem' => 0,
        'customFields' => ['field1' => ''], 'flagActive' => true, 'productDetails' => $details,
    ]];
}

beforeEach(function () {
    Cache::flush();
    config()->set([
        'esb.core.base_url' => 'https://esb.test/core',
        'esb.core.companies.BLSS' => ['username' => 'u', 'password' => 'p'],
    ]);
});

function fakeReplaceUnits(array $before, array $after): void
{
    $gets = 0;
    Http::fake([
        '*/auth/login' => Http::response(['result' => ['accessToken' => 'tok']]),
        '*/units*' => Http::response(['status' => 'ok', 'result' => ['count' => 1, 'data' => [['uomID' => 90, 'uomName' => 'Resep@150GR', 'metricID' => 2]]]]),
        '*/product/list*' => Http::response(['status' => 'ok', 'result' => ['data' => [['productID' => 7, 'productCode' => 'BW1']]]]),
        '*/product/7' => function ($request) use (&$gets, $before, $after) {
            if ($request->method() === 'PUT') {
                return Http::response(['status' => 'ok']);
            }

            return Http::response(++$gets === 1 ? $before : $after);
        },
    ]);
}

it('adds the new unit, moves the flags off the old unit and keeps it active unless asked', function (bool $deactivate) {
    fakeReplaceUnits(replaceUnitsProduct(), replaceUnitsProduct(true));

    $this->artisan('esb:replace-product-units', array_filter([
        'file' => replaceUnitsWorkbook(), '--execute' => true, '--deactivate-old' => $deactivate,
        '--report' => tempnam(sys_get_temp_dir(), 'rep'),
    ]))->expectsOutputToContain('replaced: 1')->assertSuccessful();

    $payload = Http::recorded(fn ($request) => $request->method() === 'PUT')->first()[0]->data();
    expect($payload['productDetails'])->toHaveCount(3)
        ->and($payload['productDetails'][1])->toMatchArray(['productDetailID' => 2, 'isStock' => false, 'isSales' => false, 'flagActive' => ! $deactivate])
        ->and($payload['productDetails'][2])->toMatchArray([
            'uomID' => 90, 'qty' => 150.0, 'sku' => 'BW1-Resep@150GR', 'isBase' => false,
            'isStock' => true, 'isPurchase' => true, 'isTransfer' => true, 'isSales' => true, 'flagActive' => true,
        ])->not->toHaveKey('productDetailID');
})->with([false, true]);

it('does not write on a dry run and skips unsafe products', function (?int $menuId, string $status) {
    fakeReplaceUnits(replaceUnitsProduct(menuId: $menuId), replaceUnitsProduct(true));

    $this->artisan('esb:replace-product-units', [
        'file' => replaceUnitsWorkbook(), '--report' => tempnam(sys_get_temp_dir(), 'rep'),
    ])->expectsOutputToContain("{$status}: 1")->assertSuccessful();

    expect(Http::recorded(fn ($request) => $request->method() === 'PUT'))->toHaveCount(0);
})->with([[null, 'would_replace'], [12, 'has_menu_mapping']]);

it('is idempotent when the new unit already exists', function () {
    fakeReplaceUnits(replaceUnitsProduct(true), replaceUnitsProduct(true));

    $this->artisan('esb:replace-product-units', [
        'file' => replaceUnitsWorkbook(), '--execute' => true, '--report' => tempnam(sys_get_temp_dir(), 'rep'),
    ])->expectsOutputToContain('already_done: 1')->assertSuccessful();

    expect(Http::recorded(fn ($request) => $request->method() === 'PUT'))->toHaveCount(0);
});

it('finalizes: flags only on the new unit and the old unit deactivated', function () {
    $before = replaceUnitsProduct(true);
    $before['result']['productDetails'][0]['isStock'] = true;
    $before['result']['productDetails'][2]['isStock'] = false;
    $after = replaceUnitsProduct(true);
    $after['result']['productDetails'][1]['isStock'] = false;
    $after['result']['productDetails'][1]['isPurchase'] = false;
    $after['result']['productDetails'][1]['isTransfer'] = false;
    $after['result']['productDetails'][1]['isSales'] = false;
    $after['result']['productDetails'][1]['flagActive'] = false;
    fakeReplaceUnits($before, $after);

    $this->artisan('esb:replace-product-units', [
        'file' => replaceUnitsWorkbook(), '--finalize' => true, '--execute' => true, '--report' => tempnam(sys_get_temp_dir(), 'rep'),
    ])->expectsOutputToContain('finalized: 1')->assertSuccessful();

    $details = Http::recorded(fn ($request) => $request->method() === 'PUT')->first()[0]->data()['productDetails'];
    expect($details[0])->toMatchArray(['isStock' => false, 'isSales' => false, 'flagActive' => true])
        ->and($details[1])->toMatchArray(['productDetailID' => 2, 'isStock' => false, 'flagActive' => false])
        ->and($details[2])->toMatchArray(['productDetailID' => 3, 'isStock' => true, 'isPurchase' => true, 'isTransfer' => true, 'isSales' => true, 'flagActive' => true]);
});

it('finalize ignores inactive or different-qty units that share the old unit name', function () {
    $before = replaceUnitsProduct(true);
    $stale = $before['result']['productDetails'][1];
    $stale['productDetailID'] = 9;
    $stale['qty'] = 99;
    $stale['isStock'] = $stale['isPurchase'] = $stale['isTransfer'] = $stale['isSales'] = false;
    $stale['flagActive'] = false;
    array_unshift($before['result']['productDetails'], $stale);
    fakeReplaceUnits($before, $before);

    $this->artisan('esb:replace-product-units', [
        'file' => replaceUnitsWorkbook(), '--finalize' => true, '--report' => tempnam(sys_get_temp_dir(), 'rep'),
    ])->expectsOutputToContain('would_finalize: 1')->assertSuccessful();
});

it('applies --limit to finalize runs too', function () {
    $path = tempnam(sys_get_temp_dir(), 'replace').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['Product Code', 'Unit Asal', 'Conversion Factor', 'Unit UOM', 'SKU New']));
    $writer->addRow(Row::fromValues(['BW1', 'Resep', 150, 'Resep@150GR', 'BW1-Resep@150GR']));
    $writer->addRow(Row::fromValues(['BW1', 'Resep', 150, 'Resep@150GR', 'BW1-Resep@150GR']));
    $writer->close();
    fakeReplaceUnits(replaceUnitsProduct(true), replaceUnitsProduct(true));

    $this->artisan('esb:replace-product-units', [
        'file' => $path, '--finalize' => true, '--limit' => 1, '--report' => tempnam(sys_get_temp_dir(), 'rep'),
    ])->expectsOutputToContain('would_finalize: 1')->assertSuccessful();
});

it('adds the new unit from the report factor when the product has no old unit, and guards the base unit', function (string $newUnit, string $status) {
    $path = tempnam(sys_get_temp_dir(), 'replace').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['Product Code', 'Unit Asal', 'Conversion Factor', 'Unit UOM', 'SKU New']));
    $writer->addRow(Row::fromValues(['BW1', 'Resep', 150, $newUnit, "BW1-{$newUnit}"]));
    $writer->close();

    $before = replaceUnitsProduct();
    $before['result']['productDetails'] = [$before['result']['productDetails'][0]];
    $before['result']['productDetails'][0]['isStock'] = true;
    $after = $before;
    $after['result']['productDetails'][] = ['productDetailID' => 3, 'uomID' => 90, 'uomName' => 'Resep@150GR', 'qty' => 150, 'basePrice' => 0, 'SKU' => 'x', 'cubication' => 0, 'weight' => 0, 'isBase' => false, 'isStock' => false, 'isPurchase' => false, 'isTransfer' => false, 'isSales' => false, 'menuID' => null, 'flagActive' => true];
    fakeReplaceUnits($before, $after);

    $this->artisan('esb:replace-product-units', [
        'file' => $path, '--allow-missing-source' => true, '--execute' => true, '--report' => tempnam(sys_get_temp_dir(), 'rep'),
    ])->expectsOutputToContain("{$status}: 1")->assertSuccessful();

    $puts = Http::recorded(fn ($request) => $request->method() === 'PUT');

    if ($status === 'replaced') {
        $details = $puts->first()[0]->data()['productDetails'];
        expect($details)->toHaveCount(2)
            ->and($details[0])->toMatchArray(['productDetailID' => 1, 'isStock' => true])
            ->and($details[1])->toMatchArray(['uomID' => 90, 'qty' => 150, 'isBase' => false, 'isStock' => false, 'flagActive' => true]);
    } else {
        expect($puts)->toHaveCount(0);
    }
})->with([['Resep@150GR', 'replaced'], ['Resep@150PCS', 'base_unit_mismatch']]);
