<?php

use App\Services\EsbService;
use Illuminate\Support\Facades\Http;

it('groups ESB payments by salesDateOut using half open shift boundaries', function () {
    config()->set('esb.base_url', 'https://esb.test');

    Http::fake([
        'https://esb.test/*' => Http::response([
            sale('BEFORE', '2026-07-23 08:59:59', 100),
            sale('SHIFT1', '2026-07-23 09:00:00', 1_000),
            sale('BOUNDARY', '2026-07-23 15:00:00', 2_000),
        ], 200, ['X-Pagination-Page-Count' => '1']),
    ]);

    $service = new EsbService;
    $shift1 = $service->getShiftPaymentSummary('BLOOM', '2026-07-23', '09:00', '15:00', 'secret');
    $shift2 = $service->getShiftPaymentSummary('BLOOM', '2026-07-23', '15:00', '21:00', 'secret');

    expect($shift1['rows'])->toHaveCount(1)
        ->and($shift1['rows'][0]['total'])->toBe(1000.0)
        ->and($shift1['transactions'])->toHaveCount(1)
        ->and($shift1['transactions'][0]['sales_num'])->toBe('SHIFT1')
        ->and($shift2['rows'][0]['total'])->toBe(2000.0)
        ->and($shift2['transactions'][0]['sales_num'])->toBe('BOUNDARY');
});

it('supports a shift that ends after midnight', function () {
    config()->set('esb.base_url', 'https://esb.test');

    Http::fake([
        'https://esb.test/*' => Http::response([
            sale('LATE', '2026-07-23 23:30:00', 1_000),
            sale('EARLY', '2026-07-24 01:30:00', 2_000),
            sale('END', '2026-07-24 02:00:00', 4_000),
        ], 200, ['X-Pagination-Page-Count' => '1']),
    ]);

    $summary = (new EsbService)->getShiftPaymentSummary('BLOOM', '2026-07-23', '21:00', '02:00', 'secret');

    expect($summary['rows'][0]['total'])->toBe(3000.0)
        ->and(collect($summary['transactions'])->pluck('sales_num')->all())->toBe(['LATE', 'EARLY']);
});

function sale(string $number, string $dateOut, int $amount): array
{
    return [
        'salesNum' => $number,
        'salesDateOut' => $dateOut,
        'salesPayments' => [[
            'paymentMethodName' => 'QRIS',
            'paymentMethodTypeName' => 'E-Wallet',
            'paymentAmount' => $amount,
        ]],
    ];
}
