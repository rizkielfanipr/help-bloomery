<?php

use App\Services\EsbCoreClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    Cache::flush();
    config()->set('esb.core.base_url', 'https://esb.test/core');
    config()->set('esb.core.companies.BLSS', ['username' => 'memo-user', 'password' => 'memo-secret']);
});

it('locks the confirmed field set for a Menu BOM detail response', function () {
    $fixture = internalMemoMenuBomDetailFixture();

    expect($fixture)->toHaveKeys(['bomID', 'bomCode', 'bomName', 'bomTypeName', 'productID', 'productDetailID', 'productCode', 'bomDetails'])
        ->and($fixture['bomTypeName'])->toBe('Menu')
        ->and($fixture['bomDetails'][0])->toHaveKeys(['productDetailID', 'productID', 'productCode', 'productName', 'categoryName', 'qty', 'uomName', 'tolerancePercent'])
        ->and($fixture['bomDetails'][1]['categoryName'])->toBe('Barang WIP');
});

it('locks the confirmed field set for an Assembly/WIP BOM detail response', function () {
    $fixture = internalMemoAssemblyBomDetailFixture();

    expect($fixture)->toHaveKeys(['bomID', 'bomCode', 'bomName', 'bomTypeName', 'productID', 'productDetailID', 'productCode', 'bomDetails'])
        ->and($fixture['bomTypeName'])->not->toBe('Menu');
});

it('does not assume output yield or a separate waste percentage because no consumer proves those fields exist', function () {
    $unconfirmed = ['outputQty', 'outputYield', 'yield', 'yieldQty', 'wastePercentage', 'waste', 'yieldPercent'];

    foreach ([internalMemoMenuBomDetailFixture(), internalMemoAssemblyBomDetailFixture()] as $fixture) {
        expect(array_intersect_key($fixture, array_flip($unconfirmed)))->toBe([]);
        foreach ($fixture['bomDetails'] as $detail) {
            expect(array_intersect_key($detail, array_flip($unconfirmed)))->toBe([]);
        }
    }
});

it('reads a Menu BOM detail through EsbCoreClient with BLSS context and no unfaked network call', function () {
    Cache::put('esb_core.access_token.BLSS', 'cached-token');
    Http::fake([
        'https://esb.test/core/product/bom/501' => Http::response([
            'status' => 'ok',
            'result' => internalMemoMenuBomDetailFixture(),
        ]),
    ]);

    $client = app(EsbCoreClient::class);
    $response = $client->request('BLSS', 'get', '/product/bom/501');
    $result = $client->successfulResult($response, 'mengambil detail BOM Menu', 'BLSS', '/product/bom/501');

    expect($result['bomTypeName'])->toBe('Menu')
        ->and($result['bomID'])->toBe(501);
    Http::assertSentCount(1);
});

it('reads an Assembly BOM detail through EsbCoreClient with BLSS context and no unfaked network call', function () {
    Cache::put('esb_core.access_token.BLSS', 'cached-token');
    Http::fake([
        'https://esb.test/core/product/bom/7301' => Http::response([
            'status' => 'ok',
            'result' => internalMemoAssemblyBomDetailFixture(),
        ]),
    ]);

    $client = app(EsbCoreClient::class);
    $response = $client->request('BLSS', 'get', '/product/bom/7301');
    $result = $client->successfulResult($response, 'mengambil detail BOM Assembly', 'BLSS', '/product/bom/7301');

    expect($result['bomCode'])->toBe('BOM-007301')
        ->and($result['productCode'])->toBe('BW1356');
    Http::assertSentCount(1);
});

it('fails safely and does not fall back to another Company Code when BLSS credential is missing', function () {
    config()->set('esb.core.companies.BLSS', ['username' => null, 'password' => null]);
    Http::fake();

    expect(fn () => app(EsbCoreClient::class)->request('BLSS', 'get', '/product/bom/501'))
        ->toThrow(RuntimeException::class, 'Credential ESB Core BLSS belum dikonfigurasi.');

    Http::assertNothingSent();
});
