<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

function renameWorkbook(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'rename').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->getCurrentSheet()->setName('Update Product');
    $writer->addRow(Row::fromValues(['Update Product Template']));
    $writer->addRow(Row::fromValues(['No', 'Product Name', 'Product Code', 'Unit', 'Proposed Product Name']));
    foreach ($rows as $index => [$code, $old, $new]) {
        $writer->addRow(Row::fromValues([$index + 1, $old, $code, 'GR', $new]));
        $writer->addRow(Row::fromValues([$index + 1, $old, $code, 'Resep', $new]));
    }
    $writer->close();

    return $path;
}

function renameProduct(string $name): array
{
    return ['status' => 'ok', 'result' => [
        'categoryID' => 46, 'subCategoryID' => 92, 'productName' => $name, 'productCode' => 'BW1',
        'bomID' => null, 'requestable' => true, 'purchasable' => true, 'saleable' => true, 'VAT' => false,
        'receiptTolerance' => 0, 'notes' => '', 'coretaxProductCodeID' => null, 'flagLuxuryItem' => 0,
        'customFields' => ['field1' => ''], 'flagActive' => true,
        'productDetails' => [[
            'productDetailID' => 1, 'uomID' => 5, 'qty' => 1, 'basePrice' => 0, 'SKU' => 'S1', 'cubication' => 0,
            'weight' => 0, 'isBase' => true, 'isStock' => true, 'isPurchase' => true, 'isTransfer' => true,
            'isSales' => false, 'menuID' => null, 'flagActive' => true,
        ]],
    ]];
}

beforeEach(function () {
    Cache::flush();
    config()->set([
        'esb.core.base_url' => 'https://esb.test/core',
        'esb.core.companies.BLSS' => ['username' => 'u', 'password' => 'p'],
    ]);
});

it('renames only when the ESB name equals the sheet name and keeps unit flags', function (bool $execute) {
    Http::fake([
        '*/auth/login' => Http::response(['result' => ['accessToken' => 'tok']]),
        '*/product/list*' => Http::response(['status' => 'ok', 'result' => ['data' => [['productID' => 7, 'productCode' => 'BW1']]]]),
        '*/product/7' => fn ($request) => $request->method() === 'PUT'
            ? Http::response(['status' => 'ok'])
            : Http::response(renameProduct('Central | Old')),
    ]);

    $this->artisan('esb:rename-products', array_filter([
        'file' => renameWorkbook([['BW1', 'Central | Old', 'WIP | Old']]),
        '--report' => tempnam(sys_get_temp_dir(), 'rep'),
        '--execute' => $execute,
    ]))->assertSuccessful();

    $puts = Http::recorded(fn ($request) => $request->method() === 'PUT');
    expect($puts)->toHaveCount($execute ? 1 : 0);

    if ($execute) {
        $payload = $puts->first()[0]->data();
        expect($payload['productName'])->toBe('WIP | Old')
            ->and($payload['productDetails'][0])->toMatchArray(['isStock' => true, 'isSales' => false, 'sku' => 'S1']);
    }
})->with([false, true]);

it('skips products whose ESB name differs, unchanged names and duplicate proposed names', function () {
    Http::fake([
        '*/auth/login' => Http::response(['result' => ['accessToken' => 'tok']]),
        '*/product/list*' => Http::response(['status' => 'ok', 'result' => ['data' => [['productID' => 7, 'productCode' => 'BW1']]]]),
        '*/product/7' => Http::response(renameProduct('Something else')),
    ]);

    $this->artisan('esb:rename-products', [
        'file' => renameWorkbook([
            ['BW1', 'Central | Old', 'WIP | New'],
            ['BW2', 'Same', 'Same'],
            ['BW3', 'Central | Other', 'WIP | New'],
        ]),
        '--execute' => true,
        '--report' => tempnam(sys_get_temp_dir(), 'rep'),
    ])->expectsOutputToContain('name_differs: 1')
        ->expectsOutputToContain('unchanged: 1')
        ->expectsOutputToContain('duplicate_proposed_name: 1')
        ->assertSuccessful();

    expect(Http::recorded(fn ($request) => $request->method() === 'PUT'))->toHaveCount(0);
});

it('renames products whose new name differs only by letter case', function () {
    Http::fake([
        '*/auth/login' => Http::response(['result' => ['accessToken' => 'tok']]),
        '*/product/list*' => Http::response(['status' => 'ok', 'result' => ['data' => [['productID' => 7, 'productCode' => 'BW1']]]]),
        '*/product/7' => fn ($request) => $request->method() === 'PUT'
            ? Http::response(['status' => 'ok'])
            : Http::response(renameProduct('Central | Whole cake cheese')),
    ]);

    $this->artisan('esb:rename-products', [
        'file' => renameWorkbook([['BW1', 'Central | Whole cake cheese', 'Central | Whole Cake cheese']]),
        '--execute' => true,
        '--report' => tempnam(sys_get_temp_dir(), 'rep'),
    ])->assertSuccessful();

    $puts = Http::recorded(fn ($request) => $request->method() === 'PUT');
    expect($puts)->toHaveCount(1)
        ->and($puts->first()[0]->data()['productName'])->toBe('Central | Whole Cake cheese');
});
