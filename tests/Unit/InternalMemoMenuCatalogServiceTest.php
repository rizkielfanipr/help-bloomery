<?php

use App\Services\Rnd\InternalMemo\InternalMemoMenuCatalogService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    Cache::flush();
    config()->set('esb.base_url', 'https://esb.test');
    config()->set('esb.tokens.BLSS', 'static-blss-token');
    config()->set('esb.core.base_url', 'https://esb.test/core');
    config()->set('esb.core.companies.BLSS', ['username' => 'memo-user', 'password' => 'memo-secret']);
});

/**
 * `/corev1/master/get-menu` requires a branchCode; the service resolves one via
 * EsbItemJournalService (ESB Core), so every test needs the ESB Core login + branch list faked.
 */
function fakeInternalMemoBranchList(): array
{
    return [
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'core-token']]),
        'https://esb.test/core/branch' => Http::response(['status' => 'ok', 'result' => [
            ['branchID' => 6, 'branchCode' => 'BLS', 'branchName' => 'Bloomery Pabelan'],
            ['branchID' => 10, 'branchCode' => 'BLP', 'branchName' => 'Bloomery Pakuwon'],
        ]]),
    ];
}

it('fetches a page of the Master Menu using the static BLSS token, without login to the legacy host', function () {
    Http::fake([
        ...fakeInternalMemoBranchList(),
        'https://esb.test/corev1/master/get-menu*' => Http::response([
            'status' => 'ok',
            'result' => [
                'data' => [
                    ['menuID' => 501, 'menuCode' => 'MENU-501', 'menuName' => 'Croissant Butter', 'bomID' => 42, 'bomName' => 'BOM Croissant', 'flagActive' => 1],
                    ['menuID' => 502, 'menuCode' => 'MENU-502', 'menuName' => 'Menu Belum BOM', 'bomID' => 0, 'flagActive' => 1],
                ],
                'limit' => 10,
                'count' => 2,
            ],
            'next' => null,
        ]),
    ]);

    $result = app(InternalMemoMenuCatalogService::class)->page(1, 10);

    expect($result['rows'])->toHaveCount(2)
        ->and($result['rows'][0]['menuID'])->toBe(501)
        ->and($result['rows'][0]['hasBom'])->toBeTrue()
        ->and($result['rows'][1]['hasBom'])->toBeFalse()
        ->and($result['rows'][1]['bomID'])->toBe(0)
        ->and($result['hasNext'])->toBeFalse();

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/corev1/master/get-menu')
        && $request->hasHeader('Authorization', 'Bearer static-blss-token')
        && $request['branchCode'] === 'BLS');
});

it('passes search and pagination parameters to the Master Menu endpoint', function () {
    Http::fake([
        ...fakeInternalMemoBranchList(),
        'https://esb.test/corev1/master/get-menu*' => Http::response([
            'status' => 'ok',
            'result' => ['data' => [], 'limit' => 10, 'count' => 0],
        ]),
    ]);

    app(InternalMemoMenuCatalogService::class)->page(2, 5, 'Croissant', 'MENU-5');

    Http::assertSent(function (Request $request): bool {
        if (! str_contains($request->url(), '/corev1/master/get-menu')) {
            return false;
        }

        return $request['page'] === 2
            && $request['limit'] === 5
            && $request['menuName'] === 'Croissant'
            && $request['menuCode'] === 'MENU-5'
            && $request['Boolean'] === 1
            && $request['branchCode'] === 'BLS';
    });
});

it('caches the resolved branch list so it is only looked up once across pages', function () {
    Http::fake([
        ...fakeInternalMemoBranchList(),
        'https://esb.test/corev1/master/get-menu*' => Http::response([
            'status' => 'ok',
            'result' => ['data' => [], 'limit' => 10, 'count' => 0],
        ]),
    ]);

    $catalog = app(InternalMemoMenuCatalogService::class);
    $catalog->page(1, 10);
    $catalog->page(2, 10);

    Http::assertSentCount(4); // one login + one branch lookup (cached after) + two get-menu calls
});

it('fails safely without falling back to another Company Code when the BLSS token is missing', function () {
    config()->set('esb.tokens.BLSS', '');
    Http::fake();

    expect(fn () => app(InternalMemoMenuCatalogService::class)->page())
        ->toThrow(RuntimeException::class, 'Static token ESB Master Menu BLSS belum dikonfigurasi.');

    Http::assertNothingSent();
});

it('raises a clear error when ESB has no Branch Code at all for BLSS', function () {
    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'core-token']]),
        'https://esb.test/core/branch' => Http::response(['status' => 'ok', 'result' => []]),
    ]);

    expect(fn () => app(InternalMemoMenuCatalogService::class)->page())
        ->toThrow(RuntimeException::class, 'Tidak menemukan Branch Code untuk BLSS');
});

it('raises an operational error without leaking the token when the request fails', function () {
    Http::fake([
        ...fakeInternalMemoBranchList(),
        'https://esb.test/corev1/master/get-menu*' => Http::response(['status' => 'fail', 'message' => 'Internal Server Error'], 500),
    ]);

    expect(fn () => app(InternalMemoMenuCatalogService::class)->page())
        ->toThrow(function (RuntimeException $exception) {
            expect($exception->getMessage())
                ->toContain('Gagal memuat Master Menu BLSS')
                ->not->toContain('static-blss-token');
        });
});

it('converts a connection failure into an operational error', function () {
    Http::fake([
        ...fakeInternalMemoBranchList(),
        'https://esb.test/corev1/master/get-menu*' => Http::failedConnection('origin unavailable'),
    ]);

    expect(fn () => app(InternalMemoMenuCatalogService::class)->page())
        ->toThrow(RuntimeException::class, 'Gagal menghubungi ESB Master Menu [BLSS]');
});

it('treats a Menu missing bomID entirely the same as bomID = 0', function () {
    Http::fake([
        ...fakeInternalMemoBranchList(),
        'https://esb.test/corev1/master/get-menu*' => Http::response([
            'status' => 'ok',
            'result' => ['data' => [['menuID' => 900, 'menuName' => 'Tanpa Field BOM']], 'limit' => 10, 'count' => 1],
        ]),
    ]);

    $result = app(InternalMemoMenuCatalogService::class)->page();

    expect($result['rows'][0]['bomID'])->toBe(0)
        ->and($result['rows'][0]['hasBom'])->toBeFalse();
});
