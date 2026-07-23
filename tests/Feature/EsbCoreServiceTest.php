<?php

use App\Services\EsbCoreService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set([
        'cache.default' => 'array',
        'esb.core.base_url' => 'https://core-esb.test',
        'esb.core.username' => 'integration-user',
        'esb.core.password' => 'integration-password',
        'esb.core.timeout' => 10,
        'esb.core.token_ttl' => 3300,
    ]);

    Cache::flush();
});

it('logs in automatically and reuses the cached access token', function () {
    Http::fake([
        'https://core-esb.test/auth/login' => Http::response([
            'status' => 'ok',
            'result' => ['accessToken' => 'access-token'],
        ]),
        'https://core-esb.test/product/bom*' => Http::response([
            'status' => 'ok',
            'result' => [
                'page' => 1,
                'limit' => 20,
                'count' => 1,
                'data' => [['bomID' => 424, 'bomName' => 'Croissant']],
                'prev' => '',
                'next' => '',
            ],
        ]),
    ]);

    $service = app(EsbCoreService::class);
    $first = $service->getBillOfMaterials();
    $second = $service->getBillOfMaterials();

    expect($first['data'][0]['bomID'])->toBe(424)
        ->and($second['count'])->toBe(1);

    Http::assertSentCount(3);
    Http::assertSent(fn ($request) => $request->url() === 'https://core-esb.test/auth/login');
    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://core-esb.test/product/bom')
        && $request->hasHeader('Authorization', 'Bearer access-token'));
});

it('refreshes the access token once after an unauthorized response', function () {
    Http::fake([
        'https://core-esb.test/auth/login' => Http::sequence()
            ->push(['status' => 'ok', 'result' => ['accessToken' => 'expired-token']])
            ->push(['status' => 'ok', 'result' => ['accessToken' => 'fresh-token']]),
        'https://core-esb.test/product/bom*' => Http::sequence()
            ->push(['status' => 'fail', 'message' => 'Unauthorized'], 401)
            ->push([
                'status' => 'ok',
                'result' => ['page' => 1, 'limit' => 20, 'count' => 0, 'data' => [], 'prev' => '', 'next' => ''],
            ]),
    ]);

    $result = app(EsbCoreService::class)->getBillOfMaterials();

    expect($result['data'])->toBeEmpty();

    Http::assertSentCount(4);
    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://core-esb.test/product/bom')
        && $request->hasHeader('Authorization', 'Bearer fresh-token'));
});

it('forces Assembly type when creating a recipe', function () {
    Http::fake([
        'https://core-esb.test/auth/login' => Http::response([
            'status' => 'ok',
            'result' => ['accessToken' => 'access-token'],
        ]),
        'https://core-esb.test/product/bom' => Http::response([
            'status' => 'ok',
            'result' => ['bomID' => 424],
        ]),
    ]);

    $id = app(EsbCoreService::class)->createAssembly([
        'bomTypeID' => 99,
        'bomName' => 'Assembly Croissant',
        'bomDetails' => [],
    ]);

    expect($id)->toBe(424);

    Http::assertSent(fn ($request) => $request->url() === 'https://core-esb.test/product/bom'
        && $request['bomTypeID'] === 1);
});

it('gets and updates a Bill of Material detail', function () {
    Http::fake([
        'https://core-esb.test/auth/login' => Http::response([
            'status' => 'ok',
            'result' => ['accessToken' => 'access-token'],
        ]),
        'https://core-esb.test/product/bom/42' => Http::sequence()
            ->push([
                'status' => 'ok',
                'result' => ['bomID' => 42, 'bomName' => 'Croissant'],
            ])
            ->push([
                'status' => 'ok',
                'result' => null,
            ]),
    ]);

    $service = app(EsbCoreService::class);
    $detail = $service->getBillOfMaterial(42);
    $service->updateBillOfMaterial(42, ['bomTypeID' => 1, 'bomName' => 'Updated']);

    expect($detail['bomName'])->toBe('Croissant');

    Http::assertSent(fn ($request) => $request->method() === 'PUT'
        && $request->url() === 'https://core-esb.test/product/bom/42'
        && $request['bomName'] === 'Updated');
});
