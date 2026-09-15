<?php

use App\Services\EsbGoodsReceiptService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config()->set('esb.core.base_url', 'https://esb.test/core');
    config()->set('esb.core.companies.BLSS.username', 'user');
    config()->set('esb.core.companies.BLSS.password', 'secret');
    Cache::flush();
});

test('it authenticates as BLSS and fetches receivable purchase orders', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://esb.test/core/purchase/purchase-order*' => Http::response(['status' => 'ok', 'result' => ['data' => [['purchaseNum' => 'PO-1']]]]),
    ]);

    $orders = app(EsbGoodsReceiptService::class)->purchaseOrders([
        'statusID' => EsbGoodsReceiptService::PURCHASE_ORDER_STATUS_AUTHORIZED,
    ]);

    expect($orders)->toHaveCount(1)->and($orders[0]['purchaseNum'])->toBe('PO-1');
    Http::assertSent(fn ($request): bool => $request->url() === 'https://esb.test/core/purchase/purchase-order?statusID=3'
        && $request->hasHeader('Authorization', 'Bearer token'));
});

test('it posts a goods receipt using the PO as reference number', function () {
    Cache::put('esb_core.access_token.BLSS', 'token');
    Http::preventStrayRequests();
    Http::fake(['https://esb.test/core/inventory/goods-receipt/PO-1' => Http::response([
        'status' => 'ok', 'code' => 'EC03100000', 'message' => 'Saved', 'result' => ['goodsReceiptNum' => 'GR-1'],
    ])]);

    $response = app(EsbGoodsReceiptService::class)->create('PO-1', ['locationID' => 9]);

    expect(data_get($response, 'result.goodsReceiptNum'))->toBe('GR-1');
    Http::assertSent(fn ($request): bool => $request->method() === 'POST' && $request['locationID'] === 9);
});
