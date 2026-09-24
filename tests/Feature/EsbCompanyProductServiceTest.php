<?php

use App\Services\EsbCompanyProductService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
    config([
        'esb.core.base_url' => 'https://core-esb.test',
        'esb.core.companies.BLSS' => ['username' => 'blss-user', 'password' => 'blss-secret'],
        'esb.core.companies.BLO7' => ['username' => 'blo7-user', 'password' => 'blo7-secret'],
    ]);
});

it('loads every product list page with the selected Company Code and preserves taxonomy mapping', function () {
    Http::fake(function (Request $request) {
        if ($request->url() === 'https://core-esb.test/auth/login') {
            return Http::response(['status' => 'ok', 'result' => ['accessToken' => 'blo7-token']]);
        }

        $page = (int) $request['page'];

        return Http::response(['status' => 'ok', 'result' => [
            'page' => $page,
            'limit' => 1,
            'count' => 2,
            'next' => $page === 1 ? '/product/list?page=2' : null,
            'data' => [[
                'categoryID' => 10,
                'categoryName' => 'Bahan Baku',
                'subCategoryID' => 100 + $page,
                'subCategoryName' => $page === 1 ? 'Tepung' : 'Gula',
                'productCode' => 'BBMK000'.$page,
            ]],
        ]]);
    });

    $result = app(EsbCompanyProductService::class)->taxonomy('BLO7');

    expect($result)->toBe([
        'categories' => [10 => 'Bahan Baku'],
        'subCategoriesByCategory' => [10 => [102 => 'Gula', 101 => 'Tepung']],
        'productCodesByCategory' => [10 => ['BBMK0001', 'BBMK0002']],
    ]);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://core-esb.test/auth/login'
        && $request['username'] === 'blo7-user');
    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://core-esb.test/product/list')
        && $request->hasHeader('Authorization', 'Bearer blo7-token')
        && $request['limit'] === 100
        && $request['flagActive'] === 1);
});

it('creates a product with unchanged detail and unit payload and returns the mapped raw result', function () {
    Cache::put('esb_core.access_token.BLSS', 'cached-token');
    $payload = companyProductPayload();
    Http::fake([
        'https://core-esb.test/product' => Http::response([
            'status' => 'ok',
            'result' => ['productID' => 901, 'isTemp' => true, 'ignoredByContract' => 'raw-value'],
        ]),
    ]);

    $result = app(EsbCompanyProductService::class)->create('BLSS', $payload);

    expect($result)->toBe(['productID' => 901, 'isTemp' => true]);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://core-esb.test/product'
        && $request->data() === $payload
        && $request['productDetails'][0]['uomID'] === 5
        && $request['productDetails'][0]['sku'] === 'BBMK0901-GR');
});

it('updates the selected product with unchanged payload and dynamic Company Code', function () {
    Cache::put('esb_core.access_token.BLO7', 'blo7-token');
    $payload = companyProductPayload();
    $payload['productDetails'][0]['productDetailID'] = 456;
    Http::fake([
        'https://core-esb.test/product/123' => Http::response(['status' => 'ok', 'result' => null]),
    ]);

    expect(app(EsbCompanyProductService::class)->update('BLO7', 123, $payload))->toBeNull();
    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && $request->url() === 'https://core-esb.test/product/123'
        && $request->hasHeader('Authorization', 'Bearer blo7-token')
        && $request->data() === $payload);
});

it('refreshes the Company Code token once after an explicit 401 response', function () {
    Cache::put('esb_core.access_token.BLSS', 'expired-token');
    Http::fake([
        'https://core-esb.test/product' => Http::sequence()
            ->push([], 401)
            ->push(['status' => 'ok', 'result' => ['productID' => 777, 'isTemp' => false]]),
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'fresh-token']]),
    ]);

    expect(app(EsbCompanyProductService::class)->create('BLSS', companyProductPayload()))
        ->toBe(['productID' => 777, 'isTemp' => false]);
    Http::assertSentCount(3);
    expect(Cache::get('esb_core.access_token.BLSS'))->toBe('fresh-token');
});

it('preserves validation errors from ESB with Company Code and endpoint context', function () {
    Cache::put('esb_core.access_token.BLSS', 'cached-token');
    Http::fake([
        'https://core-esb.test/product' => Http::response([
            'status' => 'fail',
            'message' => 'Validation failed',
            'errors' => [
                ['attribute' => 'productCode', 'message' => 'Product code already registered'],
                ['attribute' => 'uomID', 'message' => 'Unit is invalid'],
            ],
        ], 422),
    ]);

    expect(fn () => app(EsbCompanyProductService::class)->create('BLSS', companyProductPayload()))
        ->toThrow(RuntimeException::class, 'Gagal membuat produk [BLSS /product]: Product code already registered; Unit is invalid');
});

it('does not retry a create mutation after a connection failure with an uncertain result', function () {
    Cache::put('esb_core.access_token.BLSS', 'cached-token');
    Http::fake([
        'https://core-esb.test/product' => Http::failedConnection('connection reset after send'),
    ]);

    expect(fn () => app(EsbCompanyProductService::class)->create('BLSS', companyProductPayload()))
        ->toThrow(RuntimeException::class, 'Gagal menghubungi ESB Core [BLSS /product]');
    Http::assertSentCount(1);
});

it('rejects unsupported Company Codes before sending a request', function () {
    Http::fake();

    expect(fn () => app(EsbCompanyProductService::class)->taxonomy('UNKNOWN'))
        ->toThrow(RuntimeException::class, 'Comcode UNKNOWN tidak didukung.');
    Http::assertNothingSent();
});

it('reports a missing credential without sending product requests', function () {
    config(['esb.core.companies.BLSS' => ['username' => '', 'password' => '']]);
    Http::fake();

    expect(fn () => app(EsbCompanyProductService::class)->create('BLSS', companyProductPayload()))
        ->toThrow(RuntimeException::class, 'Credential ESB Core BLSS belum dikonfigurasi.');
    Http::assertNothingSent();
});

function companyProductPayload(): array
{
    return [
        'categoryID' => 10,
        'subCategoryID' => 11,
        'productCode' => 'BBMK0901',
        'productName' => 'Tepung Test',
        'saleable' => true,
        'productDetails' => [[
            'uomID' => 5,
            'basePrice' => 12000,
            'sku' => 'BBMK0901-GR',
            'qty' => 1,
            'isStock' => true,
            'isBase' => true,
        ]],
    ];
}
