<?php

use App\Services\EsbService;
use Illuminate\Support\Facades\Http;

it('loads and flattens active ESB product details for BOM selectors', function () {
    config()->set([
        'esb.master_product.base_url' => 'https://master-product.test',
        'esb.master_product.token' => 'static-token',
    ]);

    Http::fake([
        'https://master-product.test/corev1/master/product*' => Http::response([
            'status' => 'ok',
            'result' => [
                'page' => 1,
                'data' => [[
                    'productID' => 10,
                    'productCode' => 'BUTTER',
                    'productName' => 'Butter',
                    'receiptTolerance' => 2.5,
                    'productDetails' => [
                        [
                            'productDetailID' => 101,
                            'unit' => 'KG',
                            'conversionFactor' => 1000,
                            'basePrice' => 125000,
                            'defaultUnit' => ['baseUnit' => 'No'],
                        ],
                        [
                            'productDetailID' => 102,
                            'unit' => 'GRAM',
                            'conversionFactor' => 1,
                            'basePrice' => 125,
                            'defaultUnit' => ['baseUnit' => 'Yes'],
                        ],
                    ],
                ]],
                'next' => '',
            ],
        ]),
    ]);

    $products = (new EsbService)->getActiveProductDetails();

    expect($products)->toHaveCount(2)
        ->and($products[101])->toMatchArray([
            'productName' => 'Butter',
            'unit' => 'KG',
            'baseUnit' => 'GRAM',
            'conversionFactor' => 1000.0,
            'basePrice' => 125000.0,
            'receiptTolerance' => 2.5,
        ]);

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer static-token')
        && $request['statusActive'] === 'Yes');
});

it('loads every active product page from ESB', function () {
    config()->set([
        'esb.master_product.base_url' => 'https://master-product.test',
        'esb.master_product.token' => 'static-token',
    ]);

    Http::fake([
        'https://master-product.test/corev1/master/product*' => Http::sequence()
            ->push(masterProductPage(1, 2, 'FIRST', 101, 'next-page'))
            ->push(masterProductPage(2, 2, 'SECOND', 202, '')),
    ]);

    $products = (new EsbService)->getActiveProductDetails();

    expect(array_keys($products))->toBe([101, 202]);
    Http::assertSentCount(2);
});

it('loads one searchable product page for the modal', function () {
    config()->set([
        'esb.master_product.base_url' => 'https://master-product.test',
        'esb.master_product.token' => 'static-token',
    ]);

    Http::fake([
        'https://master-product.test/corev1/master/product*' => Http::response(
            masterProductPage(2, 30, 'CROISSANT', 303, 'next-page')
        ),
    ]);

    $result = (new EsbService)->getActiveProductDetailsPage('Croissant', 2, 'CRS');

    expect($result['page'])->toBe(2)
        ->and($result['total'])->toBe(30)
        ->and($result['hasNext'])->toBeTrue()
        ->and($result['data'])->toHaveKey(303);

    Http::assertSent(fn ($request) => $request['statusActive'] === 'Yes'
        && $request['productName'] === 'Croissant'
        && $request['productCode'] === 'CRS'
        && $request['page'] === 2);
});

it('derives the next product page when ESB omits the next URL', function () {
    config()->set([
        'esb.master_product.base_url' => 'https://master-product.test',
        'esb.master_product.token' => 'static-token',
    ]);

    Http::fake([
        'https://master-product.test/corev1/master/product*' => Http::response(
            masterProductPage(1, 3709, 'PRODUCT', 404, '')
        ),
    ]);

    $result = (new EsbService)->getActiveProductDetailsPage();

    expect($result['hasNext'])->toBeTrue();
});

it('treats an ESB product not found response as an empty result', function () {
    config()->set([
        'esb.master_product.base_url' => 'https://master-product.test',
        'esb.master_product.token' => 'static-token',
    ]);

    Http::fake([
        'https://master-product.test/corev1/master/product*' => Http::response([
            'status' => 'fail',
            'message' => 'productName not found',
            'result' => null,
        ], 400),
    ]);

    $result = (new EsbService)->getActiveProductDetailsPage('unknown');

    expect($result['data'])->toBeEmpty()
        ->and($result['total'])->toBe(0)
        ->and($result['hasNext'])->toBeFalse();
});

function masterProductPage(int $page, int $count, string $name, int $detailId, string $next): array
{
    return [
        'status' => 'ok',
        'result' => [
            'page' => $page,
            'limit' => 1,
            'count' => $count,
            'data' => [[
                'productID' => $detailId,
                'productCode' => $name,
                'productName' => $name,
                'productDetails' => [[
                    'productDetailID' => $detailId,
                    'unit' => 'PCS',
                    'basePrice' => 1,
                ]],
            ]],
            'next' => $next,
        ],
    ];
}
