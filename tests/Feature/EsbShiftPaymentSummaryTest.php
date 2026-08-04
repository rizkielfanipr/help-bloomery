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

it('caps a single CASH payment at the sale grandTotal so change given back is not counted as revenue', function () {
    config()->set('esb.base_url', 'https://esb.test');

    Http::fake([
        'https://esb.test/*' => Http::response([
            cashSale('CASH-CHANGE', '2026-08-04 09:49:00', paymentAmount: 50_000, grandTotal: 35_000),
            cashSale('CASH-EXACT', '2026-08-04 10:00:00', paymentAmount: 39_000, grandTotal: 39_000),
        ], 200, ['X-Pagination-Page-Count' => '1']),
    ]);

    $summary = (new EsbService)->getShiftPaymentSummary('BPL', '2026-08-04', '09:00', '15:00', 'secret');

    expect($summary['rows'])->toHaveCount(1)
        ->and($summary['rows'][0]['total'])->toBe(74_000.0)
        ->and($summary['transactions'][0]['payment_total'])->toBe(35_000.0)
        ->and($summary['transactions'][1]['payment_total'])->toBe(39_000.0);
});

it('does not cap CASH payments that are part of a split payment', function () {
    config()->set('esb.base_url', 'https://esb.test');

    Http::fake([
        'https://esb.test/*' => Http::response([[
            'salesNum' => 'SPLIT',
            'salesDateOut' => '2026-08-04 09:49:00',
            'grandTotal' => 100_000,
            'salesPayments' => [
                ['paymentMethodName' => 'CASH', 'paymentMethodTypeName' => 'Cash', 'paymentAmount' => 60_000],
                ['paymentMethodName' => 'QRIS', 'paymentMethodTypeName' => 'E-Wallet', 'paymentAmount' => 40_000],
            ],
        ]], 200, ['X-Pagination-Page-Count' => '1']),
    ]);

    $summary = (new EsbService)->getShiftPaymentSummary('BPL', '2026-08-04', '09:00', '15:00', 'secret');
    $totals = collect($summary['rows'])->pluck('total', 'name');

    expect($totals['CASH'])->toBe(60_000.0)
        ->and($totals['QRIS'])->toBe(40_000.0);
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

function cashSale(string $number, string $dateOut, int $paymentAmount, int $grandTotal): array
{
    return [
        'salesNum' => $number,
        'salesDateOut' => $dateOut,
        'grandTotal' => $grandTotal,
        'salesPayments' => [[
            'paymentMethodName' => 'CASH',
            'paymentMethodTypeName' => 'Cash',
            'paymentAmount' => $paymentAmount,
        ]],
    ];
}
