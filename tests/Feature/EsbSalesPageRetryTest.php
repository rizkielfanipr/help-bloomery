<?php

use App\Services\EsbService;
use Illuminate\Support\Facades\Http;

it('registers the BLSS comcode token configuration', function () {
    expect(config('esb.tokens'))->toHaveKey('BLSS');
});

it('retries a transient ESB sales server error', function () {
    config()->set('esb.base_url', 'https://core-api.esb.test');

    Http::fakeSequence()
        ->push([
            'code' => '500',
            'message' => 'An internal server error occurred.',
        ], 500)
        ->push([['salesNum' => 'SALE-001']], 200, [
            'X-Pagination-Page-Count' => '4',
        ]);

    $result = (new EsbService)->getSalesPage(
        branchCode: 'BPL',
        dateFrom: '2026-07-01',
        dateTo: '2026-07-23',
        token: 'secret-token',
        page: 5,
    );

    expect($result['data'])->toHaveCount(1)
        ->and($result['pageCount'])->toBe(4);

    Http::assertSentCount(2);
});

it('retries a transient ESB connection timeout', function () {
    config()->set('esb.base_url', 'https://core-api.esb.test');

    Http::fakeSequence()
        ->pushFailedConnection('Operation timed out')
        ->push([['salesNum' => 'SALE-002']], 200, [
            'X-Pagination-Page-Count' => '2',
        ]);

    $result = (new EsbService)->getSalesPage(
        branchCode: 'EGG',
        dateFrom: '2026-08-16',
        dateTo: '2026-08-19',
        token: 'secret-token',
        page: 2,
    );

    expect($result['data'])->toHaveCount(1)
        ->and($result['pageCount'])->toBe(2);

    Http::assertSentCount(2);
});

it('converts a persistent ESB connection timeout into a readable error', function () {
    config()->set('esb.base_url', 'https://core-api.esb.test');

    Http::fakeSequence()
        ->pushFailedConnection('Operation timed out')
        ->pushFailedConnection('Operation timed out')
        ->pushFailedConnection('Operation timed out');

    expect(fn () => (new EsbService)->getSalesPage(
        branchCode: 'EGG',
        dateFrom: '2026-08-16',
        dateTo: '2026-08-19',
        token: 'secret-token',
        page: 2,
    ))->toThrow(RuntimeException::class, 'ESB tidak dapat dihubungi untuk branch EGG halaman 2');

    Http::assertSentCount(3);
});
