<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

function recipeUnitWorkbook(array $codes): string
{
    $path = tempnam(sys_get_temp_dir(), 'recipe').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['Product Code', 'BOM Name']));
    foreach ($codes as $code) {
        $writer->addRow(Row::fromValues([$code, 'x']));
    }
    $writer->close();

    return $path;
}

function recipeUnitProduct(bool $recipeDone): array
{
    $detail = fn (int $id, int $uom, bool $flags, bool $base) => [
        'productDetailID' => $id, 'uomID' => $uom, 'qty' => $base ? 1 : 150, 'basePrice' => 0, 'SKU' => "S{$id}",
        'cubication' => 0, 'weight' => 0, 'isBase' => $base, 'isStock' => $flags, 'isPurchase' => $flags,
        'isTransfer' => $flags, 'isSales' => $flags, 'menuID' => null, 'flagActive' => true,
    ];

    return ['status' => 'ok', 'result' => [
        'categoryID' => 46, 'subCategoryID' => 92, 'productName' => 'WIP | Milk Pudding', 'productCode' => 'BW1183',
        'bomID' => null, 'requestable' => true, 'purchasable' => true, 'saleable' => true, 'VAT' => false,
        'receiptTolerance' => 0, 'notes' => '', 'coretaxProductCodeID' => null, 'flagLuxuryItem' => 0,
        'customFields' => ['field1' => ''], 'flagActive' => true,
        'productDetails' => [$detail(1, 5, ! $recipeDone, true), $detail(2, 16, $recipeDone, false)],
    ]];
}

beforeEach(function () {
    Cache::flush();
    config()->set([
        'esb.core.base_url' => 'https://esb.test/core',
        'esb.core.companies.BLSS' => ['username' => 'u', 'password' => 'p'],
    ]);
});

it('moves the stock, purchase, transfer and sales flags to the recipe unit only when executing', function (bool $execute) {
    Http::fake([
        '*/auth/login' => Http::response(['result' => ['accessToken' => 'tok']]),
        '*/product/list*' => Http::response(['status' => 'ok', 'result' => ['data' => [['productID' => 5402, 'productCode' => 'BW1183']]]]),
        '*/product/5402' => fn ($request) => $request->method() === 'PUT'
            ? Http::response(['status' => 'ok'])
            : Http::response(recipeUnitProduct(false)),
    ]);

    $this->artisan('esb:set-recipe-unit', array_filter([
        'file' => recipeUnitWorkbook(['BW1183']),
        '--report' => tempnam(sys_get_temp_dir(), 'rep'),
        '--execute' => $execute,
    ]))->assertSuccessful();

    $puts = Http::recorded(fn ($request) => $request->method() === 'PUT');

    if (! $execute) {
        expect($puts)->toHaveCount(0);

        return;
    }

    expect($puts)->toHaveCount(1);
    $payload = $puts->first()[0]->data();
    expect($payload['vat'])->toBeFalse()
        ->and($payload['productDetails'][0])->toMatchArray(['isBase' => true, 'isStock' => false, 'isPurchase' => false, 'isTransfer' => false, 'isSales' => false, 'sku' => 'S1'])
        ->and($payload['productDetails'][1])->toMatchArray(['isBase' => false, 'isStock' => true, 'isPurchase' => true, 'isTransfer' => true, 'isSales' => true]);
})->with([false, true]);

it('skips products that are already converted', function () {
    Http::fake([
        '*/auth/login' => Http::response(['result' => ['accessToken' => 'tok']]),
        '*/product/list*' => Http::response(['status' => 'ok', 'result' => ['data' => [['productID' => 5402, 'productCode' => 'BW1183']]]]),
        '*/product/5402' => Http::response(recipeUnitProduct(true)),
    ]);

    $this->artisan('esb:set-recipe-unit', [
        'file' => recipeUnitWorkbook(['BW1183']),
        '--report' => tempnam(sys_get_temp_dir(), 'rep'),
        '--execute' => true,
    ])->expectsOutputToContain('already_done: 1')->assertSuccessful();

    expect(Http::recorded(fn ($request) => $request->method() === 'PUT'))->toHaveCount(0);
});

it('reports codes that are not found in ESB', function () {
    Http::fake([
        '*/auth/login' => Http::response(['result' => ['accessToken' => 'tok']]),
        '*/product/list*' => Http::response(['status' => 'ok', 'result' => ['data' => []]]),
    ]);

    $this->artisan('esb:set-recipe-unit', [
        'file' => recipeUnitWorkbook(['NOPE']),
        '--report' => tempnam(sys_get_temp_dir(), 'rep'),
    ])->expectsOutputToContain('not_found: 1')->assertSuccessful();
});

it('uses the active recipe unit when a product has several recipe units', function () {
    $product = recipeUnitProduct(false);
    $inactive = $product['result']['productDetails'][1];
    $inactive['productDetailID'] = 3;
    $inactive['flagActive'] = false;
    $product['result']['productDetails'][] = $inactive;

    Http::fake([
        '*/auth/login' => Http::response(['result' => ['accessToken' => 'tok']]),
        '*/product/list*' => Http::response(['status' => 'ok', 'result' => ['data' => [['productID' => 5402, 'productCode' => 'BW1183']]]]),
        '*/product/5402' => fn ($request) => $request->method() === 'PUT'
            ? Http::response(['status' => 'ok'])
            : Http::response($product),
    ]);

    $this->artisan('esb:set-recipe-unit', [
        'file' => recipeUnitWorkbook(['BW1183']),
        '--report' => tempnam(sys_get_temp_dir(), 'rep'),
        '--execute' => true,
    ])->expectsOutputToContain('updated: 1')->assertSuccessful();

    $details = Http::recorded(fn ($request) => $request->method() === 'PUT')->first()[0]->data()['productDetails'];
    expect($details[1]['isPurchase'])->toBeTrue()
        ->and($details[2]['isPurchase'])->toBeFalse();
});

it('omits an empty bomID from the update payload and keeps a real one', function (int $bomId, bool $expectsBomId) {
    $product = recipeUnitProduct(false);
    $product['result']['bomID'] = $bomId;

    Http::fake([
        '*/auth/login' => Http::response(['result' => ['accessToken' => 'tok']]),
        '*/product/list*' => Http::response(['status' => 'ok', 'result' => ['data' => [['productID' => 5402, 'productCode' => 'BW1183']]]]),
        '*/product/5402' => fn ($request) => $request->method() === 'PUT'
            ? Http::response(['status' => 'ok'])
            : Http::response($product),
    ]);

    $this->artisan('esb:set-recipe-unit', [
        'file' => recipeUnitWorkbook(['BW1183']),
        '--report' => tempnam(sys_get_temp_dir(), 'rep'),
        '--execute' => true,
    ])->assertSuccessful();

    $payload = Http::recorded(fn ($request) => $request->method() === 'PUT')->first()[0]->data();
    expect(array_key_exists('bomID', $payload))->toBe($expectsBomId);
})->with([[0, false], [77, true]]);
