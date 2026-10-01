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

it('passes Code search and pagination parameters to the Master Menu endpoint (proven to filter server-side)', function () {
    Http::fake([
        ...fakeInternalMemoBranchList(),
        'https://esb.test/corev1/master/get-menu*' => Http::response([
            'status' => 'ok',
            'result' => ['data' => [], 'limit' => 20, 'count' => 0],
        ]),
    ]);

    app(InternalMemoMenuCatalogService::class)->page(2, 5, '', 'MENU-5');

    Http::assertSent(function (Request $request): bool {
        if (! str_contains($request->url(), '/corev1/master/get-menu')) {
            return false;
        }

        return $request['page'] === 2
            && $request['limit'] === 5
            && ($request['menuName'] ?? null) === null
            && $request['menuCode'] === 'MENU-5'
            && $request['Boolean'] === 1
            && $request['branchCode'] === 'BLS';
    });
});

it('reports the real server page size (20) as perPage instead of the ignored requested limit', function () {
    Http::fake([
        ...fakeInternalMemoBranchList(),
        'https://esb.test/corev1/master/get-menu*' => Http::response([
            'status' => 'ok',
            // The live API always returns limit=20 regardless of the requested limit — proven via
            // a direct request against the real ESB host (Phase 0-style contract check).
            'result' => ['data' => [], 'limit' => 20, 'count' => 1386],
        ]),
    ]);

    $result = app(InternalMemoMenuCatalogService::class)->page(1, 10);

    expect($result['perPage'])->toBe(20);
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

it('caches a Code-search page result so a repeat call does not re-hit ESB', function () {
    Http::fake([
        ...fakeInternalMemoBranchList(),
        'https://esb.test/corev1/master/get-menu*' => Http::response([
            'status' => 'ok',
            'result' => ['data' => [['menuID' => 501, 'menuName' => 'Croissant Butter']], 'limit' => 20, 'count' => 1],
        ]),
    ]);

    $catalog = app(InternalMemoMenuCatalogService::class);
    $first = $catalog->page(1, 10, '', 'MENU-5');
    $second = $catalog->page(1, 10, '', 'MENU-5');

    expect($second)->toBe($first);
    Http::assertSentCount(3); // one login + one branch lookup + a single get-menu call, reused for the repeat
});

it('does not reuse the Code-search cache across a different code or page', function () {
    Http::fake([
        ...fakeInternalMemoBranchList(),
        'https://esb.test/corev1/master/get-menu*' => Http::response([
            'status' => 'ok',
            'result' => ['data' => [], 'limit' => 10, 'count' => 0],
        ]),
    ]);

    $catalog = app(InternalMemoMenuCatalogService::class);
    $catalog->page(1, 10, '', 'MENU-5');
    $catalog->page(1, 10, '', 'MENU-9');
    $catalog->page(2, 10, '', 'MENU-5');

    Http::assertSentCount(5); // one login + one branch lookup + three distinct get-menu calls
});

/**
 * Name search no longer sends `menuName` to ESB at all (proven live: the endpoint silently
 * ignores it and returns its full unfiltered catalog). Instead the whole catalog is paged through
 * once, cached, and filtered/paginated locally — the same fix EsbBillOfMaterialService uses for a
 * listing with no reliable server-side filter.
 */
it('finds a Menu by name even when it is on a later ESB page than requested, by fetching and filtering the whole catalog', function () {
    Http::fake([
        ...fakeInternalMemoBranchList(),
        'https://esb.test/corev1/master/get-menu?page=1*' => Http::response([
            'status' => 'ok',
            'result' => ['data' => [
                ['menuID' => 1, 'menuName' => 'Americano', 'menuCode' => 'A1'],
                ['menuID' => 2, 'menuName' => 'Cappuccino', 'menuCode' => 'A2'],
            ], 'limit' => 2, 'count' => 4],
            'next' => 'http://esb.test/corev1/master/get-menu?page=2',
        ]),
        'https://esb.test/corev1/master/get-menu?page=2*' => Http::response([
            'status' => 'ok',
            'result' => ['data' => [
                ['menuID' => 3, 'menuName' => 'Croissant Butter', 'menuCode' => 'B1'],
                ['menuID' => 4, 'menuName' => 'Donut Chocolate', 'menuCode' => 'B2'],
            ], 'limit' => 2, 'count' => 4],
            'next' => null,
        ]),
    ]);

    $result = app(InternalMemoMenuCatalogService::class)->page(1, 10, 'Croissant');

    expect($result['rows'])->toHaveCount(1)
        ->and($result['rows'][0]['menuName'])->toBe('Croissant Butter')
        ->and($result['total'])->toBe(1);

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/corev1/master/get-menu') && filled($request['menuName'] ?? null));
});

it('builds the full-catalog cache only once, reusing it across different Name searches and pages', function () {
    Http::fake([
        ...fakeInternalMemoBranchList(),
        'https://esb.test/corev1/master/get-menu*' => Http::response([
            'status' => 'ok',
            'result' => ['data' => [
                ['menuID' => 1, 'menuName' => 'Americano', 'menuCode' => 'A1'],
                ['menuID' => 2, 'menuName' => 'Croissant Butter', 'menuCode' => 'B1'],
            ], 'limit' => 20, 'count' => 2],
        ]),
    ]);

    $catalog = app(InternalMemoMenuCatalogService::class);
    $catalog->page(1, 10, 'Americano');
    $catalog->page(1, 10, 'Croissant');
    $catalog->page(2, 10, 'Americano');

    // one login + one branch lookup + a single get-menu call to build the full catalog once,
    // reused by every subsequent Name search/page combination.
    Http::assertSentCount(3);
});

it('also filters by Code when both Name and Code are given during a local Name search', function () {
    Http::fake([
        ...fakeInternalMemoBranchList(),
        'https://esb.test/corev1/master/get-menu*' => Http::response([
            'status' => 'ok',
            'result' => ['data' => [
                ['menuID' => 1, 'menuName' => 'Croissant Butter', 'menuCode' => 'A1'],
                ['menuID' => 2, 'menuName' => 'Croissant Almond', 'menuCode' => 'B2'],
            ], 'limit' => 20, 'count' => 2],
        ]),
    ]);

    $result = app(InternalMemoMenuCatalogService::class)->page(1, 10, 'Croissant', 'A1');

    expect($result['rows'])->toHaveCount(1)
        ->and($result['rows'][0]['menuCode'])->toBe('A1');
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
