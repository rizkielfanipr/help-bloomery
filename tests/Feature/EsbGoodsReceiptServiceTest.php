<?php

use App\Services\EsbGoodsReceiptService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Cache::flush();
    config()->set('esb.core.base_url', 'https://esb.test/core');
    config()->set('esb.core.companies.BLSS', ['username' => 'receiving-user', 'password' => 'secret']);
});

it('keeps purchase order location and creation contracts on the shared client', function () {
    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://esb.test/core/purchase/purchase-order?*' => Http::response(['status' => 'ok', 'result' => [
            'data' => [['purchaseNum' => 'PO-1']],
        ]]),
        'https://esb.test/core/purchase/purchase-order/PO-1' => Http::response(['status' => 'ok', 'result' => [
            'purchaseNum' => 'PO-1', 'branchID' => 17,
        ]]),
        'https://esb.test/core/location?*' => Http::response(['status' => 'ok', 'result' => [
            ['locationID' => 10, 'locationName' => 'Kitchen'],
        ]]),
        'https://esb.test/core/inventory/goods-receipt/PO-1' => Http::response([
            'status' => 'ok', 'code' => 'OK', 'message' => 'OK', 'result' => ['goodsReceiptNum' => 'GR-1'],
        ]),
    ]);
    $service = app(EsbGoodsReceiptService::class);

    expect($service->purchaseOrders(['statusID' => 3]))->toBe([['purchaseNum' => 'PO-1']])
        ->and($service->purchaseOrder('PO-1')['branchID'])->toBe(17)
        ->and($service->locations(17))->toBe([['locationID' => 10, 'locationName' => 'Kitchen']]);

    $created = $service->create('PO-1', ['goodsReceiptDate' => '2026-09-23']);
    expect($created['result']['goodsReceiptNum'])->toBe('GR-1')
        ->and($created['response']['code'])->toBe('OK');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://esb.test/core/inventory/goods-receipt/PO-1'
        && $request['goodsReceiptDate'] === '2026-09-23');
});

it('uses the shared client token refresh for Receiving requests', function () {
    $loginCount = 0;
    $requestCount = 0;
    Http::fake(function (Request $request) use (&$loginCount, &$requestCount) {
        if (str_ends_with($request->url(), '/auth/login')) {
            $loginCount++;

            return Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token-'.$loginCount]]);
        }

        $requestCount++;

        return $requestCount === 1
            ? Http::response(['status' => 'fail', 'message' => 'Unauthorized'], 401)
            : Http::response(['status' => 'ok', 'result' => ['data' => []]]);
    });

    expect(app(EsbGoodsReceiptService::class)->purchaseOrders())->toBe([])
        ->and($loginCount)->toBe(2)
        ->and($requestCount)->toBe(2);
});

it('reports Receiving endpoint context when ESB rejects a request', function () {
    Cache::put('esb_core.access_token.BLSS', 'token');
    Http::fake([
        'https://esb.test/core/location*' => Http::response(['status' => 'fail', 'message' => 'Branch tidak ditemukan'], 422),
    ]);

    expect(fn () => app(EsbGoodsReceiptService::class)->locations(999))
        ->toThrow(RuntimeException::class, 'Gagal mengambil lokasi [BLSS /location]: Branch tidak ditemukan');
});
