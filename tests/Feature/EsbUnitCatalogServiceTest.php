<?php

use App\Services\EsbUnitCatalogService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
    config()->set([
        'esb.core.base_url' => 'https://core-esb.test',
        'esb.core.companies.BLSS' => ['username' => 'user', 'password' => 'secret'],
        'esb.core.uoms' => [2 => 'PCS', 5 => 'GR'],
    ]);
});

it('loads every unit page from BLSS and caches the complete catalog', function () {
    Http::fake(function (Request $request) {
        if ($request->url() === 'https://core-esb.test/auth/login') {
            return Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]);
        }

        if ($request->url() === 'https://core-esb.test/units?page=1&limit=1000') {
            return Http::response(['status' => 'ok', 'result' => [
                'page' => 1,
                'limit' => 2,
                'count' => 3,
                'next' => 2,
                'data' => [
                    ['uomID' => 5, 'uomName' => 'GR'],
                    ['uomID' => 81, 'uomName' => 'PACK@3PCS'],
                ],
            ]]);
        }

        return Http::response(['status' => 'ok', 'result' => [
            'page' => 2,
            'limit' => 2,
            'count' => 3,
            'next' => null,
            'data' => [['uomID' => 90, 'uomName' => 'Resep@100GR']],
        ]]);
    });

    $service = app(EsbUnitCatalogService::class);
    $first = $service->options();
    $second = $service->options();

    expect($first)->toBe([
        5 => 'GR',
        81 => 'PACK@3PCS',
        2 => 'PCS',
        90 => 'Resep@100GR',
    ])->and($second)->toBe($first);

    expect(Http::recorded(fn (Request $request): bool => str_contains($request->url(), '/units')))->toHaveCount(2);
});

it('uses configured units when the BLSS unit catalog cannot be loaded', function () {
    Http::fake([
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://core-esb.test/units*' => Http::response(['status' => 'fail', 'message' => 'Service unavailable'], 503),
    ]);

    expect(app(EsbUnitCatalogService::class)->options())->toBe([
        2 => 'PCS',
        5 => 'GR',
    ]);
});
