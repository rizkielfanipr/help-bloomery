<?php

use App\Models\Branch;
use App\Services\EsbStockMovementService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 9, 17));
    config()->set('esb.core.base_url', 'https://stock-esb.test');
    Http::preventStrayRequests();
    $this->branch = Branch::factory()->create();
    $this->pair = $this->branch->esbCodes()->create([
        'esb_comcode' => 'BLSS', 'esb_branch_code' => 'BLA', 'is_active' => true,
    ]);
    $this->branch->update(['stock_card_esb_code_id' => $this->pair->id]);
    Cache::put('esb_core.access_token.BLSS', 'blss-token', 300);
});

it('uses the mapped company token and branch with explicit numeric pagination', function () {
    Http::fake(function ($request) {
        $page = (int) $request['page'];

        return Http::response(['status' => 'ok', 'result' => [
            'count' => 101, 'limit' => 0, 'next' => $page === 1 ? 'https://invalid.test/?page=bad' : '',
            'data' => [['branchCode' => 'BLA', 'productCode' => 'P-'.$page]],
        ]]);
    });

    $rows = app(EsbStockMovementService::class)->movements($this->pair, '2026-09-01', '2026-09-17');

    expect($rows)->toHaveCount(2);
    Http::assertSentCount(2);
    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://stock-esb.test/report/stock-movement?')
        && $request->hasHeader('Authorization', 'Bearer blss-token')
        && $request['branchCode'] === 'BLA'
        && $request['unitToShow'] === 'Default Stock Unit'
        && $request['startPeriod'] === '2026-09-01'
        && (int) $request['limit'] === 100
        && (int) $request['page'] === 2);
});

it('takes the latest balance per location instead of adding historical balances', function () {
    $row = ['branchCode' => 'BLA', 'productCode' => 'P-1', 'UOM' => 'GR', 'createdDate' => '2026-09-16 10:00:00'];
    Http::fake(['*stock-movement*' => Http::response(['status' => 'ok', 'result' => [
        'data' => [
            $row + ['location' => 'Kitchen', 'documentDate' => '2026-09-16', 'qtyBalance' => 7],
            $row + ['location' => 'Kitchen', 'documentDate' => '2026-09-15', 'qtyBalance' => 100],
            $row + ['location' => 'Warehouse', 'documentDate' => '2026-09-16', 'qtyBalance' => 3],
        ], 'next' => '', 'count' => 3,
    ]])]);

    $result = app(EsbStockMovementService::class)->balancesForBranch($this->branch, '2026-09-17', 'stockUnit');

    expect($result['rows'][0]['totalQty'])->toBe(10.0);
    Http::assertSent(fn ($request): bool => $request['startPeriod'] === '2026-09-17' && $request['endPeriod'] === '2026-09-17');
});

it('uses only the explicitly selected Stock Card source when multiple mappings are active', function () {
    $selected = $this->branch->esbCodes()->create(['esb_comcode' => 'BLO6', 'esb_branch_code' => 'BL6', 'is_active' => true]);
    $this->branch->update(['stock_card_esb_code_id' => $selected->id]);
    Cache::put('esb_core.access_token.BLO6', 'blo6-token', 300);
    Http::fake(fn ($request) => Http::response(['status' => 'ok', 'result' => [
        'data' => [[
            'branchCode' => $request['branchCode'], 'productCode' => 'P-1', 'UOM' => 'GR',
            'location' => 'Kitchen', 'documentDate' => '2026-09-17', 'createdDate' => '2026-09-17 10:00:00', 'qtyBalance' => 5,
        ]], 'next' => '', 'count' => 1,
    ]]));

    $result = app(EsbStockMovementService::class)->balancesForBranch($this->branch, '2026-09-17', 'stockUnit');

    expect($result['rows'][0]['totalQty'])->toBe(5.0);
    Http::assertSentCount(1);
    Http::assertSent(fn ($request): bool => $request['startPeriod'] === '2026-09-17' && $request['endPeriod'] === '2026-09-17');
    Http::assertSent(fn ($request): bool => $request['branchCode'] === 'BL6' && $request->hasHeader('Authorization', 'Bearer blo6-token'));
    Http::assertNotSent(fn ($request): bool => $request['branchCode'] === 'BLA');
});

it('rejects responses outside the mapped branch', function () {
    Http::fake(['*stock-movement*' => Http::response(['status' => 'ok', 'result' => [
        'data' => [['branchCode' => 'OTHER']], 'count' => 1,
    ]])]);

    expect(fn () => app(EsbStockMovementService::class)->movements($this->pair, '2026-09-01', '2026-09-17'))
        ->toThrow(RuntimeException::class, 'di luar cabang');
});

it('rejects missing mapping without falling back to all branches', function () {
    $this->pair->esb_branch_code = '';

    expect(fn () => app(EsbStockMovementService::class)->movements($this->pair, '2026-09-01', '2026-09-17'))
        ->toThrow(RuntimeException::class, 'belum lengkap');
    Http::assertNothingSent();
});

it('refreshes an expired company token once and stops if authorization keeps failing', function () {
    config()->set('esb.core.companies.BLSS', ['username' => 'test-user', 'password' => 'test-password']);
    Http::fake([
        '*auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'fresh-token']]),
        '*stock-movement*' => Http::response(['status' => 'fail', 'message' => 'Unauthorized'], 401),
    ]);

    expect(fn () => app(EsbStockMovementService::class)->movements($this->pair, '2026-09-01', '2026-09-17'))
        ->toThrow(RuntimeException::class, 'Unauthorized');
    Http::assertSentCount(3);
    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'stock-movement') && $request->hasHeader('Authorization', 'Bearer fresh-token'));
});

it('does not combine balances with different units', function () {
    Http::fake(['*stock-movement*' => Http::response(['status' => 'ok', 'result' => [
        'data' => [
            ['branchCode' => 'BLA', 'productCode' => 'P-1', 'UOM' => 'GR', 'location' => 'Kitchen', 'qtyBalance' => 5],
            ['branchCode' => 'BLA', 'productCode' => 'P-1', 'UOM' => 'KG', 'location' => 'Warehouse', 'qtyBalance' => 2],
        ], 'next' => '', 'count' => 2,
    ]])]);

    expect(fn () => app(EsbStockMovementService::class)->balancesForBranch($this->branch, '2026-09-17', 'stockUnit'))
        ->toThrow(RuntimeException::class, 'Satuan saldo');
});

it('aggregates all transaction types across pages from the selected mapping', function () {
    Http::fake(function ($request) {
        $page = (int) $request['page'];
        $row = [
            'branchCode' => $request['branchCode'], 'productCode' => 'P-1', 'UOM' => 'GR',
            'location' => 'Kitchen', 'documentDate' => '2026-09-17', 'qtyBalance' => 5,
            'transactionType' => $page === 1 ? 'Goods Receipt' : 'Waste',
            'qtyIn' => $page === 1 ? 10 : 0, 'qtyOut' => $page === 1 ? 0 : 2,
        ];

        return Http::response(['status' => 'ok', 'result' => [
            'data' => [$row], 'count' => 101, 'next' => $page === 1 ? 'next' : '',
        ]]);
    });

    $result = app(EsbStockMovementService::class)->balancesForBranch($this->branch, '2026-09-17', 'stockUnit');

    expect($result['types'])->toBe(['Beginning', 'Goods Delivery', 'Goods Receipt', 'POS Sales', 'Purchase Invoice Adjustment', 'Waste'])
        ->and($result['transactions']['P-1']['Goods Receipt']['qty_in'])->toBe(10.0)
        ->and($result['transactions']['P-1']['Waste']['qty_out'])->toBe(2.0)
        ->and($result['rows'][0]['totalQty'])->toBe(5.0);
    Http::assertSentCount(2);
});

it('requires an explicit active Stock Card source', function () {
    $this->branch->update(['stock_card_esb_code_id' => null]);

    expect(fn () => app(EsbStockMovementService::class)->balancesForBranch($this->branch->fresh(), '2026-09-17', 'stockUnit'))
        ->toThrow(RuntimeException::class, 'Sumber Stock Card belum diatur');
});
