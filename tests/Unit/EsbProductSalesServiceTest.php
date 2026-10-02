<?php

use App\Services\EsbProductSalesService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    Cache::flush();
    // tests/Pest.php's global stray-request guard is scoped ->in('Feature') only; this file lives
    // in tests/Unit (matching the existing RndInternalMemoBomContractTest.php precedent, which has
    // the same gap), so it is set explicitly here instead of relying on that global hook.
    Http::preventStrayRequests();
    config()->set('esb.core.base_url', 'https://esb.test/core');
    config()->set('esb.core.companies.BLSS', ['username' => 'sales-user', 'password' => 'sales-secret']);
});

/** @return array<string, mixed> */
function productSalesRowFixture(array $overrides = []): array
{
    return array_replace([
        'productSalesNum' => 'SL-00123',
        'productSalesDate' => '2026-10-01',
        'requiredDate' => '2026-10-05',
        'branchID' => 6,
        'branchName' => 'Bloomery Pabelan',
        'customerID' => 'CUST-01',
        'customerName' => 'Budi Santoso',
        'customerAddress' => 'Jl. Merdeka No. 1',
        'productSalesTotal' => 500000,
        'currencySign' => 'Rp',
        'statusID' => '1',
        'statusName' => 'Open',
        'createdBy' => 'admin.store',
        'linkPurchaseNum' => null,
        'additionalInfo' => null,
    ], $overrides);
}

it('sends the exact lookup parameters: Company Code, branchID, productSalesNum, page 1, small limit', function () {
    Cache::put('esb_core.access_token.BLSS', 'cached-token');
    Http::fake([
        'https://esb.test/core/sales/product-sales*' => Http::response(['status' => 'ok', 'result' => ['data' => [productSalesRowFixture()]]]),
    ]);

    app(EsbProductSalesService::class)->exactLookup('BLSS', 6, 'SL-00123');

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/sales/product-sales')
            && $request->hasHeader('Authorization', 'Bearer cached-token')
            && $request['productSalesNum'] === 'SL-00123'
            && $request['branchID'] === 6
            && $request['page'] === 1
            && (int) $request['limit'] <= 10;
    });
});

it('returns the normalized row on an exact productSalesNum + branch match', function () {
    Cache::put('esb_core.access_token.BLSS', 'cached-token');
    Http::fake([
        'https://esb.test/core/sales/product-sales*' => Http::response(['status' => 'ok', 'result' => ['data' => [productSalesRowFixture()]]]),
    ]);

    $result = app(EsbProductSalesService::class)->exactLookup('BLSS', 6, 'SL-00123');

    expect($result)->not->toBeNull()
        ->and($result['product_sales_number'])->toBe('SL-00123')
        ->and($result['esb_branch_id'])->toBe(6)
        ->and($result['customer_name'])->toBe('Budi Santoso')
        ->and($result['total'])->toBe(500.0 * 1000)
        ->and($result['raw'])->toBeArray();
});

it('rejects the first row and finds the real match further down the page, never trusting row order', function () {
    Cache::put('esb_core.access_token.BLSS', 'cached-token');
    Http::fake([
        'https://esb.test/core/sales/product-sales*' => Http::response(['status' => 'ok', 'result' => ['data' => [
            productSalesRowFixture(['productSalesNum' => 'SL-WRONG', 'branchID' => 6]),
            productSalesRowFixture(['productSalesNum' => 'SL-00123', 'branchID' => 6]),
        ]]]),
    ]);

    $result = app(EsbProductSalesService::class)->exactLookup('BLSS', 6, 'SL-00123');

    expect($result['product_sales_number'])->toBe('SL-00123');
});

it('rejects a row that matches the number but not the branch', function () {
    Cache::put('esb_core.access_token.BLSS', 'cached-token');
    Http::fake([
        'https://esb.test/core/sales/product-sales*' => Http::response(['status' => 'ok', 'result' => ['data' => [
            productSalesRowFixture(['productSalesNum' => 'SL-00123', 'branchID' => 999]),
        ]]]),
    ]);

    $result = app(EsbProductSalesService::class)->exactLookup('BLSS', 6, 'SL-00123');

    expect($result)->toBeNull();
});

it('returns null when ESB has no data at all for this page', function () {
    Cache::put('esb_core.access_token.BLSS', 'cached-token');
    Http::fake([
        'https://esb.test/core/sales/product-sales*' => Http::response(['status' => 'ok', 'result' => ['data' => []]]),
    ]);

    expect(app(EsbProductSalesService::class)->exactLookup('BLSS', 6, 'SL-00123'))->toBeNull();
});

it('also accepts result being the row list directly, in case the real envelope has no data key', function () {
    Cache::put('esb_core.access_token.BLSS', 'cached-token');
    Http::fake([
        'https://esb.test/core/sales/product-sales*' => Http::response(['status' => 'ok', 'result' => [productSalesRowFixture()]]),
    ]);

    expect(app(EsbProductSalesService::class)->exactLookup('BLSS', 6, 'SL-00123'))->not->toBeNull();
});

it('refreshes once after a 401 and succeeds on retry', function () {
    Cache::put('esb_core.access_token.BLSS', 'stale-token');
    Http::fake([
        'https://esb.test/core/sales/product-sales*' => Http::sequence()
            ->push(['status' => 'fail', 'message' => 'Unauthorized'], 401)
            ->push(['status' => 'ok', 'result' => ['data' => [productSalesRowFixture()]]], 200),
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'fresh-token']]),
    ]);

    $result = app(EsbProductSalesService::class)->exactLookup('BLSS', 6, 'SL-00123');

    expect($result)->not->toBeNull();
    Http::assertSentCount(3); // first 401 attempt + login + retry
});

it('converts a connection failure into an operational error without losing the caller to a crash', function () {
    Cache::put('esb_core.access_token.BLSS', 'cached-token');
    Http::fake(['https://esb.test/core/sales/product-sales*' => Http::failedConnection('timed out')]);

    expect(fn () => app(EsbProductSalesService::class)->exactLookup('BLSS', 6, 'SL-00123'))
        ->toThrow(RuntimeException::class, 'Gagal menghubungi ESB Core');
});

it('raises an operational error on a malformed/failed response, without leaking the token', function () {
    Cache::put('esb_core.access_token.BLSS', 'cached-token');
    Http::fake(['https://esb.test/core/sales/product-sales*' => Http::response(['status' => 'fail', 'message' => 'Internal Server Error'], 500)]);

    expect(fn () => app(EsbProductSalesService::class)->exactLookup('BLSS', 6, 'SL-00123'))
        ->toThrow(function (RuntimeException $exception) {
            expect($exception->getMessage())
                ->toContain('mencari Sales Order')
                ->not->toContain('cached-token');
        });
});

it('rejects a blank Sales Order number before making any HTTP call', function () {
    Http::fake();

    expect(fn () => app(EsbProductSalesService::class)->exactLookup('BLSS', 6, '   '))
        ->toThrow(RuntimeException::class, 'Nomor Sales Order wajib diisi.');

    Http::assertNothingSent();
});

it('blocks a stray request that is not faked, proving tests stay isolated from the real network', function () {
    Cache::put('esb_core.access_token.BLSS', 'cached-token');
    // A specific, unrelated pattern — not a bare Http::fake(), which would catch-all respond
    // instead of leaving /sales/product-sales genuinely stray.
    Http::fake(['https://esb.test/core/some-other-endpoint' => Http::response(['status' => 'ok'])]);

    expect(fn () => app(EsbProductSalesService::class)->exactLookup('BLSS', 6, 'SL-00123'))
        ->toThrow(StrayRequestException::class);
});
