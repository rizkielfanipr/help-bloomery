<?php

use App\Services\EsbBillOfMaterialService;
use App\Services\EsbCoreService;
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

it('preserves BOM filters pagination and compatibility delegation', function () {
    Http::fake([
        '*/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        '*/product/bom*' => Http::response(['status' => 'ok', 'result' => [
            'page' => 2, 'limit' => 5000, 'count' => 5001, 'data' => [['bomID' => 8]],
            'prev' => '/prev', 'next' => '',
        ]]),
    ]);

    $result = app(EsbCoreService::class)->getBillOfMaterials([
        'page' => 2, 'limit' => 9000, 'bomID' => 8, 'productName' => 'Cake',
        'uomName' => 'PCS', 'sort' => 'bomName', 'flagActive' => 0,
    ]);

    expect($result['limit'])->toBe(5000)->and($result['next'])->toBeNull();
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/product/bom')
        && $request['limit'] === 5000 && $request['flagActive'] === 0);
});

it('loads all BOM pages and returns raw detail', function () {
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/auth/login')) {
            return Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]);
        }
        if (str_ends_with($request->url(), '/product/bom/9')) {
            return Http::response(['status' => 'ok', 'result' => ['bomID' => 9, 'bomDetails' => [['ID' => 1]]]]);
        }
        $page = (int) ($request['page'] ?? 1);

        return Http::response(['status' => 'ok', 'result' => [
            'page' => $page, 'limit' => 100, 'count' => 101, 'data' => [['bomID' => $page]],
            'prev' => '', 'next' => $page === 1 ? '/next' : '',
        ]]);
    });

    $service = app(EsbBillOfMaterialService::class);
    expect($service->getAllBillOfMaterials())->toHaveCount(2)
        ->and($service->getBillOfMaterial(9)['bomDetails'][0]['ID'])->toBe(1);
});

it('preserves default and explicit BOM types for create and update payloads', function () {
    Cache::put('esb_core.access_token', 'token');
    Http::fake([
        'https://core-esb.test/product/bom' => Http::response(['status' => 'ok', 'result' => ['bomID' => 41]]),
        'https://core-esb.test/product/bom/41' => Http::response(['status' => 'ok', 'result' => null]),
    ]);

    $service = app(EsbBillOfMaterialService::class);
    expect($service->createAssembly(['bomName' => 'Assembly']))->toBe(41);
    $service->createAssembly(['bomTypeID' => 3, 'bomName' => 'Menu']);
    $service->updateBillOfMaterial(41, ['bomTypeID' => 1, 'bomName' => 'Updated']);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request['bomTypeID'] === 1);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request['bomTypeID'] === 3);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT' && $request['bomName'] === 'Updated');
});

it('does not retry a BOM mutation after validation or connection failure', function (string $failure, string $exceptionClass) {
    Cache::put('esb_core.access_token', 'token');
    Http::fake(['https://core-esb.test/product/bom' => $failure === 'validation'
        ? Http::response(['status' => 'fail', 'errors' => [['message' => 'BOM detail invalid']]], 422)
        : Http::failedConnection('connection reset')]);

    expect(fn () => app(EsbBillOfMaterialService::class)->createAssembly(['bomName' => 'Invalid']))->toThrow($exceptionClass);
    Http::assertSentCount(1);
})->with([
    ['validation', RuntimeException::class],
    ['connection', ConnectionException::class],
]);

it('rejects a successful create response without a BOM ID', function () {
    Cache::put('esb_core.access_token', 'token');
    Http::fake(['https://core-esb.test/product/bom' => Http::response(['status' => 'ok', 'result' => []])]);

    expect(fn () => app(EsbBillOfMaterialService::class)->createAssembly([]))
        ->toThrow(RuntimeException::class, 'ESB tidak mengembalikan ID Bill of Material.');
});
