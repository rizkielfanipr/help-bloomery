<?php

use App\Services\EsbMasterProductService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
    config([
        'cache.default' => 'array',
        'esb.core.base_url' => 'https://core-esb.test',
        'esb.core.username' => 'global-user',
        'esb.core.password' => 'global-password',
    ]);
});

it('preserves list filters pagination and compatibility delegation', function () {
    Http::fake([
        '*/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        '*/product/list*' => Http::response(['status' => 'ok', 'result' => [
            'page' => 2, 'limit' => 100, 'count' => 101, 'data' => [['productID' => 9]],
            'prev' => '/prev', 'next' => '',
        ]]),
    ]);

    $result = app(EsbMasterProductService::class)->getProducts([
        'page' => 2, 'limit' => 500, 'productName' => 'Tepung', 'productCode' => 'BB01',
        'categoryID' => 10, 'subCategoryID' => 11,
    ]);

    expect($result)->toBe([
        'page' => 2, 'limit' => 100, 'count' => 101, 'data' => [['productID' => 9]],
        'prev' => '/prev', 'next' => null,
    ]);
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/product/list')
        && $request['page'] === 2 && $request['limit'] === 100
        && $request['productName'] === 'Tepung' && $request['flagActive'] === 1);
});

it('loads every product page and uses the authoritative endpoint for id lookup', function () {
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/auth/login')) {
            return Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]);
        }
        if ($request->url() === 'https://core-esb.test/product/2') {
            return Http::response(['status' => 'ok', 'result' => [
                'productID' => 2,
                'productName' => 'Product 2',
                'productDetails' => [['productDetailID' => 22, 'uomID' => 5]],
            ]]);
        }
        $page = (int) ($request['page'] ?? 1);
        $name = $request['productName'] ?? null;

        return Http::response(['status' => 'ok', 'result' => [
            'page' => $page, 'limit' => 100, 'count' => $name ? 2 : 101,
            'data' => $name
                ? [['productID' => 1, 'productName' => 'Other'], ['productID' => 2, 'productName' => ' TEPUNG TEST ']]
                : [['productID' => $page, 'productName' => 'Product '.$page]],
            'prev' => '', 'next' => ! $name && $page === 1 ? '/next' : '',
        ]]);
    });

    $service = app(EsbMasterProductService::class);
    expect($service->getAllProducts())->toHaveCount(2)
        ->and($service->findProductById(2)['productName'])->toBe('Product 2')
        ->and($service->findProductByExactName('tepung test')['productID'])->toBe(2);
});

it('deduplicates legacy product details by product and unit while preferring an active row', function () {
    $details = [
        ['productID' => 81, 'productDetailID' => 101, 'uomID' => 5, 'unit' => 'GR', 'flagActive' => true],
        ['productID' => 81, 'productDetailID' => 102, 'uomID' => 5, 'unit' => 'GR', 'flagActive' => false],
        ['productID' => 81, 'productDetailID' => 103, 'uomID' => 9, 'unit' => 'PCS', 'flagActive' => true],
        ['productID' => 81, 'productDetailID' => 104, 'unitID' => 16, 'uomName' => 'Resep', 'flagActive' => true],
    ];

    $result = app(EsbMasterProductService::class)->deduplicateProductDetails($details);

    expect(array_keys($result))->toBe([101, 103, 104]);
});

it('keeps taxonomy cache and concurrent page requests', function () {
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/auth/login')) {
            return Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]);
        }
        $page = (int) ($request['page'] ?? 1);

        return Http::response(['status' => 'ok', 'result' => [
            'page' => $page, 'limit' => 100, 'count' => 201,
            'data' => [[
                'categoryID' => $page, 'categoryName' => 'Category '.$page,
                'subCategoryID' => 10 + $page, 'subCategoryName' => 'Sub '.$page,
            ]],
            'prev' => '', 'next' => $page < 3 ? '/next' : '',
        ]]);
    });

    $service = app(EsbMasterProductService::class);
    $first = $service->getProductTaxonomy();
    $second = $service->getProductTaxonomy();

    expect($first['categories'])->toBe([1 => 'Category 1', 2 => 'Category 2', 3 => 'Category 3'])
        ->and($second)->toBe($first);
    Http::assertSentCount(4);
});

it('suggests the next product code using active and inactive products', function () {
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/auth/login')) {
            return Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]);
        }

        $isActive = (int) $request['flagActive'] === 1;

        return Http::response(['status' => 'ok', 'result' => [
            'page' => 1,
            'limit' => 100,
            'count' => 1,
            'data' => [['productCode' => $isActive ? 'BW1385' : 'BW1386']],
            'prev' => null,
            'next' => null,
        ]]);
    });

    expect(app(EsbMasterProductService::class)->suggestNextProductCode(20))
        ->toBe('BW1387');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/product/list')
        && (int) $request['flagActive'] === 0);
});

it('preserves create and update payloads and mapped create response', function () {
    Cache::put('esb_core.access_token', 'token');
    $payload = ['productCode' => 'BB01', 'productDetails' => [['uomID' => 5]]];
    Http::fake([
        'https://core-esb.test/product' => Http::response(['status' => 'ok', 'result' => ['productID' => 81, 'isTemp' => true]]),
        'https://core-esb.test/product/81' => Http::response(['status' => 'ok', 'result' => null]),
    ]);

    $service = app(EsbMasterProductService::class);
    expect($service->createProduct($payload))->toBe(['productID' => 81, 'isTemp' => true])
        ->and($service->updateProduct(81, $payload))->toBeNull();
    Http::assertSent(fn (Request $request): bool => $request->data() === $payload);
});

it('deactivates an old Product Detail before creating a replacement conversion factor', function () {
    Cache::put('esb_core.access_token', 'token');
    $oldDetail = [
        'productDetailID' => 101,
        'uomID' => 9,
        'uomName' => 'PCS',
        'qty' => 61.53,
        'basePrice' => 0,
        'SKU' => 'BW1398-PCS',
        'cubication' => 0,
        'weight' => 0,
        'isBase' => false,
        'isStock' => true,
        'isPurchase' => true,
        'isTransfer' => true,
        'isSales' => true,
        'menuID' => null,
        'flagActive' => true,
    ];
    $inactiveDetail = array_merge($oldDetail, [
        'isStock' => false,
        'isPurchase' => false,
        'isTransfer' => false,
        'isSales' => false,
        'flagActive' => false,
    ]);
    $newDetail = array_merge($oldDetail, [
        'productDetailID' => 202,
        'qty' => 60,
    ]);

    Http::fake([
        'https://core-esb.test/product/81' => Http::sequence()
            ->push(['status' => 'ok', 'result' => null])
            ->push(['status' => 'ok', 'result' => ['productID' => 81, 'productDetails' => [$inactiveDetail]]])
            ->push(['status' => 'ok', 'result' => null])
            ->push(['status' => 'ok', 'result' => ['productID' => 81, 'productDetails' => [$inactiveDetail, $newDetail]]]),
    ]);

    $payload = [
        'productCode' => 'BW1398',
        'productName' => 'WIP Test',
        'productDetails' => [[
            'productDetailID' => 101,
            'uomID' => 9,
            'qty' => 60,
            'basePrice' => 0,
            'sku' => 'BW1398-PCS',
            'cubication' => 0,
            'weight' => 0,
            'isBase' => false,
            'isStock' => true,
            'isPurchase' => true,
            'isTransfer' => true,
            'isSales' => true,
            'menuID' => null,
            'flagActive' => true,
        ]],
    ];

    $updated = app(EsbMasterProductService::class)->updateProductWithUnitReplacements(
        81,
        $payload,
        ['productID' => 81, 'productDetails' => [$oldDetail]],
    );

    expect(data_get($updated, 'productDetails.1.productDetailID'))->toBe(202);

    $putRequests = Http::recorded(fn (Request $request): bool => $request->method() === 'PUT')->values();
    expect($putRequests)->toHaveCount(2)
        ->and(data_get($putRequests[0][0]->data(), 'productDetails.0'))->toMatchArray([
            'productDetailID' => 101,
            'qty' => 61.53,
            'flagActive' => false,
            'isStock' => false,
        ])
        ->and(data_get($putRequests[1][0]->data(), 'productDetails.0'))->toMatchArray([
            'productDetailID' => 101,
            'flagActive' => false,
        ])
        ->and(data_get($putRequests[1][0]->data(), 'productDetails.1'))->toMatchArray([
            'uomID' => 9,
            'qty' => 60,
            'flagActive' => true,
        ])
        ->and(data_get($putRequests[1][0]->data(), 'productDetails.1'))->not->toHaveKey('productDetailID');
});

it('filters modal product units using the authoritative product detail status', function () {
    Cache::put('esb_core.access_token', 'token');
    Http::fake([
        'https://core-esb.test/product/4215' => Http::response(['status' => 'ok', 'result' => [
            'productDetails' => [
                ['productDetailID' => 4890, 'flagActive' => true],
                ['productDetailID' => 4891, 'flagActive' => false],
                ['productDetailID' => 7310, 'flagActive' => true],
            ],
        ]]),
    ]);

    $details = [
        4890 => ['productID' => 4215, 'productDetailID' => 4890, 'unit' => 'GR'],
        4891 => ['productID' => 4215, 'productDetailID' => 4891, 'unit' => 'Resep Lama'],
        7310 => ['productID' => 4215, 'productDetailID' => 7310, 'unit' => 'Toples'],
    ];

    $result = app(EsbMasterProductService::class)->filterActiveProductDetails($details);

    expect(array_keys($result))->toBe([4890, 7310]);
});

it('refreshes a token once after an explicit 401', function () {
    Cache::put('esb_core.access_token', 'expired');
    Http::fake([
        'https://core-esb.test/product/list*' => Http::sequence()
            ->push(['status' => 'fail'], 401)
            ->push(['status' => 'ok', 'result' => ['page' => 1, 'limit' => 20, 'count' => 0, 'data' => [], 'prev' => '', 'next' => '']]),
        '*/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'fresh']]),
    ]);

    expect(app(EsbMasterProductService::class)->getProducts()['data'])->toBeEmpty()
        ->and(Cache::get('esb_core.access_token'))->toBe('fresh');
    Http::assertSentCount(3);
});

it('does not retry a product mutation after a connection failure', function () {
    Cache::put('esb_core.access_token', 'token');
    Http::fake(['https://core-esb.test/product' => Http::failedConnection('connection reset')]);

    expect(fn () => app(EsbMasterProductService::class)->createProduct(['productCode' => 'BB01']))
        ->toThrow(ConnectionException::class);
    Http::assertSentCount(1);
});

it('preserves ESB product validation errors without retrying the mutation', function () {
    Cache::put('esb_core.access_token', 'token');
    Http::fake(['https://core-esb.test/product' => Http::response([
        'status' => 'fail',
        'errors' => [['attribute' => 'productCode', 'message' => 'Product code already registered']],
    ], 422)]);

    expect(fn () => app(EsbMasterProductService::class)->createProduct(['productCode' => 'BB01']))
        ->toThrow(RuntimeException::class, 'Gagal membuat Master Product: Product code already registered');
    Http::assertSentCount(1);
});

it('rejects a successful create response without a Product ID', function () {
    Cache::put('esb_core.access_token', 'token');
    Http::fake(['https://core-esb.test/product' => Http::response([
        'status' => 'ok', 'result' => ['isTemp' => false],
    ])]);

    expect(fn () => app(EsbMasterProductService::class)->createProduct(['productCode' => 'BB01']))
        ->toThrow(RuntimeException::class, 'ESB tidak mengembalikan Product ID.');
});
