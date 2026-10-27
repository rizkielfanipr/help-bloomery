<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

function componentAuditWorkbook(): string
{
    $path = tempnam(sys_get_temp_dir(), 'audit').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->getCurrentSheet()->setName('Menu Memakai BBMK061');
    $writer->addRow(Row::fromValues(['Comcode', 'Menu ID', 'BOM ID', 'Memakai BBMK061 (Matcha Powder)']));
    $writer->addRow(Row::fromValues(['BLO7', 1, 10, 'Langsung']));
    $writer->addRow(Row::fromValues(['BLO7', 2, 11, 'Tidak langsung (lewat WIP)']));
    $writer->addRow(Row::fromValues(['BLO7', 3, 10, 'Langsung']));
    $writer->close();

    return $path;
}

function componentBom(array $components): array
{
    return ['status' => 'ok', 'result' => [
        'bomID' => 10, 'bomName' => 'Matcha Latte', 'bomTypeID' => 3, 'bomTypeName' => 'Menu', 'productDetailID' => -1,
        'editedDate' => '2026-01-01T00:00:00+07:00',
        'bomDetails' => array_map(fn (array $c, int $i): array => $c + [
            'ID' => 100 + $i, 'lastHpp' => 5, 'yieldPercent' => 0, 'tolerancePercent' => 0, 'printGroup' => '', 'subtitution' => null,
        ], $components, array_keys($components)),
    ]];
}

beforeEach(function () {
    Cache::flush();
    config()->set([
        'esb.core.base_url' => 'https://esb.test/core',
        'esb.core.companies.BLO7' => ['username' => 'u', 'password' => 'p'],
    ]);
});

function fakeComponentEsb(array $components): void
{
    Http::fake([
        '*/auth/login' => Http::response(['result' => ['accessToken' => 'tok']]),
        '*/product/list*' => Http::response(['status' => 'ok', 'result' => ['data' => [['productID' => 9, 'productCode' => 'BBMK501']]]]),
        '*/product/9' => Http::response(['status' => 'ok', 'result' => ['productDetails' => [
            ['productDetailID' => 900, 'uomID' => 5, 'uomName' => 'GR', 'basePrice' => 42, 'flagActive' => true],
            ['productDetailID' => 901, 'uomID' => 3, 'uomName' => 'PACK', 'basePrice' => 0, 'flagActive' => false],
        ]]]),
        '*/product/bom/10' => Http::response(componentBom($components)),
    ]);
}

it('lists the BOMs to change in a dry run, once per BOM and only for the chosen status', function () {
    fakeComponentEsb([
        ['productDetailID' => 1, 'productCode' => 'BBMK061', 'productName' => 'Matcha Powder', 'uomName' => 'GR', 'qty' => 2.5],
        ['productDetailID' => 2, 'productCode' => 'BBMS001', 'productName' => 'Milk', 'uomName' => 'ML', 'qty' => 100],
    ]);

    $this->artisan('esb:replace-bom-component', ['file' => componentAuditWorkbook(), '--report' => tempnam(sys_get_temp_dir(), 'rep')])
        ->expectsOutputToContain('would_update: 1')
        ->assertSuccessful();

    expect(Http::recorded(fn ($request) => $request->method() === 'PUT'))->toHaveCount(0);
});

it('skips BOMs that already have the target, lack the unit or do not hold the component', function (array $components, string $status) {
    fakeComponentEsb($components);

    $this->artisan('esb:replace-bom-component', ['file' => componentAuditWorkbook(), '--report' => tempnam(sys_get_temp_dir(), 'rep')])
        ->expectsOutputToContain("{$status}: 1")
        ->assertSuccessful();
})->with([
    'target already there' => [[['productDetailID' => 1, 'productCode' => 'BBMK061', 'productName' => 'M', 'uomName' => 'GR', 'qty' => 1], ['productDetailID' => 900, 'productCode' => 'BBMK501', 'productName' => 'K', 'uomName' => 'GR', 'qty' => 1]], 'target_already_in_bom'],
    'unit missing on target' => [[['productDetailID' => 1, 'productCode' => 'BBMK061', 'productName' => 'M', 'uomName' => 'ML', 'qty' => 1]], 'no_matching_unit'],
    'component absent' => [[['productDetailID' => 2, 'productCode' => 'BBMS001', 'productName' => 'Milk', 'uomName' => 'ML', 'qty' => 1]], 'component_not_found'],
]);

it('swaps the component through the BOM update action keeping qty and the other components', function () {
    $before = [
        ['productDetailID' => 1, 'productCode' => 'BBMK061', 'productName' => 'Matcha Powder', 'uomName' => 'GR', 'qty' => 2.5],
        ['productDetailID' => 2, 'productCode' => 'BBMS001', 'productName' => 'Milk', 'uomName' => 'ML', 'qty' => 100],
    ];
    $after = [
        ['productDetailID' => 900, 'productCode' => 'BBMK501', 'productName' => 'Matcha Powder Komorebi', 'uomName' => 'GR', 'qty' => 2.5],
        $before[1],
    ];
    $gets = 0;

    Http::fake([
        '*/auth/login' => Http::response(['result' => ['accessToken' => 'tok']]),
        '*/product/list*' => Http::response(['status' => 'ok', 'result' => ['data' => [['productID' => 9, 'productCode' => 'BBMK501']]]]),
        '*/product/9' => Http::response(['status' => 'ok', 'result' => ['productDetails' => [['productDetailID' => 900, 'uomID' => 5, 'uomName' => 'GR', 'basePrice' => 42, 'flagActive' => true]]]]),
        '*/product/bom/10' => function ($request) use (&$gets, $before, $after) {
            if ($request->method() === 'PUT') {
                return Http::response(['status' => 'ok', 'result' => []]);
            }

            return Http::response(componentBom(++$gets <= 2 ? $before : $after));
        },
    ]);

    $this->artisan('esb:replace-bom-component', ['file' => componentAuditWorkbook(), '--execute' => true, '--report' => tempnam(sys_get_temp_dir(), 'rep')])
        ->expectsOutputToContain('updated: 1')
        ->assertSuccessful();

    $payload = Http::recorded(fn ($request) => $request->method() === 'PUT')->first()[0]->data();
    expect(array_column($payload['bomDetails'], 'productDetailID'))->toBe([900, 2])
        ->and(array_column($payload['bomDetails'], 'qty'))->toBe([2.5, 100.0])
        ->and($payload['bomDetails'][0]['lastHPP'])->toBe(42.0)
        ->and($payload['bomDetails'][1]['lastHPP'])->toBe(5.0);
});
