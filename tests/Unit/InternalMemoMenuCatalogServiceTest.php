<?php

use App\Services\Rnd\InternalMemo\InternalMemoMenuCatalogService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config()->set('esb.base_url', 'https://esb.test');
    config()->set('esb.tokens.BLSS', 'static-blss-token');
});

it('loads every page for the requested Company and Branch context', function () {
    Http::fake(function (Request $request) {
        $page = (int) $request['page'];

        return Http::response(['status' => 'ok', 'result' => [
            'data' => [[
                'menuID' => $page,
                'menuCode' => 'MENU-'.$page,
                'menuName' => 'Menu '.$page,
                'bomID' => $page === 1 ? 100 : 0,
                'flagActive' => 1,
            ]],
            'limit' => 1,
            'count' => 2,
        ]]);
    });

    $result = app(InternalMemoMenuCatalogService::class)->allForContext('blss', 'bls');

    expect($result)->toHaveCount(2)
        ->and($result[0]['hasBom'])->toBeTrue()
        ->and($result[1]['hasBom'])->toBeFalse();
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer static-blss-token')
        && $request['branchCode'] === 'BLS');
});

it('filters inactive and invalid Menu rows and removes duplicates within one context', function () {
    Http::fake([
        'https://esb.test/corev1/master/get-menu*' => Http::response(['status' => 'ok', 'result' => [
            'data' => [
                ['menuID' => 10, 'menuName' => 'Aktif', 'flagActive' => 1],
                ['menuID' => 10, 'menuName' => 'Duplikat', 'flagActive' => 1],
                ['menuID' => 11, 'menuName' => 'Nonaktif', 'flagActive' => 0],
                ['menuID' => 0, 'menuName' => 'Tidak Valid', 'flagActive' => 1],
            ],
            'limit' => 20,
            'count' => 4,
        ]]),
    ]);

    $result = app(InternalMemoMenuCatalogService::class)->allForContext('BLSS', 'BLS');

    expect($result)->toHaveCount(1)
        ->and($result[0]['menuName'])->toBe('Aktif');
});

it('requires a static token for the requested Company Code without falling back', function () {
    config()->set('esb.tokens.BLO6', '');
    Http::fake();

    expect(fn () => app(InternalMemoMenuCatalogService::class)->allForContext('BLO6', 'BL6'))
        ->toThrow(RuntimeException::class, 'Static token ESB Master Menu BLO6 belum dikonfigurasi.');
    Http::assertNothingSent();
});

it('reports Company and Branch context without leaking the token when ESB fails', function () {
    Http::fake([
        'https://esb.test/corev1/master/get-menu*' => Http::response(['status' => 'fail', 'message' => 'Unavailable'], 500),
    ]);

    expect(fn () => app(InternalMemoMenuCatalogService::class)->allForContext('BLSS', 'BLS'))
        ->toThrow(function (RuntimeException $exception): void {
            expect($exception->getMessage())
                ->toContain('BLSS/BLS')
                ->not->toContain('static-blss-token');
        });
});

it('converts a connection failure into an operational error with context', function () {
    Http::fake([
        'https://esb.test/corev1/master/get-menu*' => Http::failedConnection('origin unavailable'),
    ]);

    expect(fn () => app(InternalMemoMenuCatalogService::class)->allForContext('BLSS', 'BLS'))
        ->toThrow(RuntimeException::class, 'Gagal menghubungi ESB Master Menu [BLSS/BLS]');
});
