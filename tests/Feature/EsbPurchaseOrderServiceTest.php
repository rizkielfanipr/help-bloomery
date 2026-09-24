<?php

use App\Services\EsbPurchaseOrderService;
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

it('preserves Purchase Order list filters pagination and global credentials', function () {
    Http::fake([
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'global-token']]),
        'https://core-esb.test/purchase/purchase-order*' => Http::response(['status' => 'ok', 'result' => [
            'page' => 2,
            'limit' => 100,
            'count' => 101,
            'data' => [['purchaseNum' => 'PO-101']],
            'prev' => '/previous',
            'next' => '',
        ]]),
    ]);

    $result = app(EsbPurchaseOrderService::class)->getPurchaseOrders([
        'page' => 2,
        'limit' => 500,
        'purchaseNum' => 'PO-101',
        'branchID' => 17,
        'supplierID' => 22,
        'statusID' => 8,
        'dateFrom' => '2026-09-01',
        'dateTo' => '2026-09-30',
    ]);

    expect($result)->toBe([
        'page' => 2,
        'limit' => 100,
        'count' => 101,
        'data' => [['purchaseNum' => 'PO-101']],
        'prev' => '/previous',
        'next' => null,
    ]);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://core-esb.test/auth/login'
        && $request->data() === ['username' => 'global-user', 'password' => 'global-password']);
    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://core-esb.test/purchase/purchase-order')
        && $request->hasHeader('Authorization', 'Bearer global-token')
        && $request['page'] === 2
        && $request['limit'] === 100
        && $request['sort'] === '-purchaseDate'
        && $request['branchID'] === 17);
});

it('returns the raw Purchase Order detail and encodes its number', function () {
    Cache::put('esb_core.access_token', 'cached-token');
    $detail = ['purchaseNum' => 'PO TEST/01', 'purchaseDetails' => [['ID' => 10]]];
    Http::fake([
        'https://core-esb.test/purchase/purchase-order/PO%20TEST%2F01' => Http::response(['status' => 'ok', 'result' => $detail]),
    ]);

    expect(app(EsbPurchaseOrderService::class)->getPurchaseOrder('PO TEST/01'))->toBe($detail);
});

it('refreshes the global token once after a 401 response', function () {
    Cache::put('esb_core.access_token', 'expired-token');
    Http::fake([
        'https://core-esb.test/purchase/purchase-order*' => Http::sequence()
            ->push(['status' => 'fail', 'message' => 'Unauthorized'], 401)
            ->push(['status' => 'ok', 'result' => ['page' => 1, 'limit' => 100, 'count' => 0, 'data' => [], 'prev' => '', 'next' => '']]),
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'fresh-token']]),
    ]);

    expect(app(EsbPurchaseOrderService::class)->getPurchaseOrders()['data'])->toBeEmpty()
        ->and(Cache::get('esb_core.access_token'))->toBe('fresh-token');
    Http::assertSentCount(3);
});

it('preserves ESB validation errors and does not retry a non-401 response', function () {
    Cache::put('esb_core.access_token', 'cached-token');
    Http::fake([
        'https://core-esb.test/purchase/purchase-order*' => Http::response([
            'status' => 'fail',
            'errors' => [['attribute' => 'dateFrom', 'message' => 'Date format is invalid']],
        ], 422),
    ]);

    expect(fn () => app(EsbPurchaseOrderService::class)->getPurchaseOrders())
        ->toThrow(RuntimeException::class, 'Gagal mengambil daftar Purchase Order: Date format is invalid');
    Http::assertSentCount(1);
});

it('fails before a request when global credentials are missing', function () {
    config(['esb.core.username' => '', 'esb.core.password' => '']);
    Http::fake();

    expect(fn () => app(EsbPurchaseOrderService::class)->getPurchaseOrders())
        ->toThrow(RuntimeException::class, 'Konfigurasi koneksi ESB Core belum lengkap.');
    Http::assertNothingSent();
});

it('does not retry a Purchase Order request after a connection failure', function () {
    Cache::put('esb_core.access_token', 'cached-token');
    Http::fake([
        'https://core-esb.test/purchase/purchase-order*' => Http::failedConnection('connection reset'),
    ]);

    expect(fn () => app(EsbPurchaseOrderService::class)->getPurchaseOrders())
        ->toThrow(ConnectionException::class);
    Http::assertSentCount(1);
});
