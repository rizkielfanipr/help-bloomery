<?php

use App\Services\EsbService;
use Illuminate\Support\Facades\Http;

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
