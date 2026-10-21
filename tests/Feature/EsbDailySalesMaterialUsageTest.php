<?php

use App\Services\EsbService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set(['esb.base_url' => 'https://sales-esb.test', 'esb.token' => 'global-token']);
});

it('requests daily material usage for one branch, date, and unit with the pair token', function () {
    Http::fake(['https://sales-esb.test/corev1/sales/get-daily-sales-material-usage*' => Http::response([
        ['branchCode' => 'BPL', 'salesDate' => '2026-10-18', 'productCode' => '0001', 'productName' => 'Croissant Dough', 'totalQty' => '12.5', 'unit' => 'PCS', 'totalConversionQty' => '12.5', 'unitConversion' => 'PCS'],
    ])]);

    $rows = (new EsbService)->getDailySalesMaterialUsage('BPL', '2026-10-18', 'stockUnit', 'pair-token');

    expect($rows)->toHaveCount(1)->and($rows[0]['productCode'])->toBe('0001');
    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://sales-esb.test/corev1/sales/get-daily-sales-material-usage')
        && $request['branchCode'] === 'BPL' && $request['salesDate'] === '2026-10-18' && $request['flagUnit'] === 'stockUnit'
        && $request->header('Authorization')[0] === 'Bearer pair-token');
});

it('throws on an ESB error response', function () {
    Http::fake(['*' => Http::response(['message' => 'down'], 503)]);

    expect(fn () => (new EsbService)->getDailySalesMaterialUsage('BPL', '2026-10-18', 'stockUnit', 'pair-token'))
        ->toThrow(RuntimeException::class, 'ESB Material Usage API error: 503');
});
