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

it('loads every product page and preserves exact name and id lookup', function () {
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/auth/login')) {
            return Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]);
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
