<?php

use App\Enums\SalesReportStatus;
use App\Models\Branch;
use App\Models\SalesReport;
use App\Models\SalesReportEntry;
use App\Models\SalesReportReconciliation;
use Illuminate\Support\Facades\Http;

it('flags submitted reports whose stored CASH total no longer matches ESB, without changing any data', function () {
    config()->set(['esb.tokens.TESTCO' => 'branch-token']);

    $branch = Branch::factory()->create([
        'name' => 'Bloomery Pabelan',
        'esb_branch_code' => 'BPL',
        'esb_comcode' => 'TESTCO',
    ]);

    $report = SalesReport::create([
        'branch_id' => $branch->id,
        'report_date' => '2026-08-04',
        'submitted_at' => now(),
        'status' => SalesReportStatus::PendingSupervisor->value,
    ]);
    SalesReportEntry::create([
        'sales_report_id' => $report->id,
        'shift_number' => 1,
        'payment_method_name' => 'CASH',
        'sales_store_amount' => 100_000,
    ]);
    $reconciliation = SalesReportReconciliation::create([
        'sales_report_id' => $report->id,
        'payment_method_name' => 'CASH',
        'reported_store_amount' => 100_000,
        'store_amount' => 100_000,
        'system_amount' => 100_000,
    ]);

    Http::fake(['*' => Http::response([[
        'salesNum' => 'SBPL1',
        'salesDateOut' => '2026-08-04 09:49:26',
        'grandTotal' => 35_000,
        'salesPayments' => [[
            'paymentMethodName' => 'CASH',
            'paymentMethodTypeName' => 'Cash',
            'paymentAmount' => 50_000,
        ]],
    ], [
        'salesNum' => 'SBPL2',
        'salesDateOut' => '2026-08-04 14:51:06',
        'grandTotal' => 39_000,
        'salesPayments' => [[
            'paymentMethodName' => 'CASH',
            'paymentMethodTypeName' => 'Cash',
            'paymentAmount' => 50_000,
        ]],
    ]], 200, ['X-Pagination-Page-Count' => '1'])]);

    $this->artisan('sales-reports:audit-cash-discrepancy', [
        '--from' => '2026-08-04',
        '--to' => '2026-08-04',
    ])
        ->expectsOutputToContain('Bloomery Pabelan')
        ->expectsOutputToContain('terindikasi terdampak')
        ->assertSuccessful();

    expect($reconciliation->fresh()->system_amount)->toEqual(100_000);
});

it('reports no discrepancy when the stored CASH total already matches ESB', function () {
    config()->set(['esb.tokens.TESTCO' => 'branch-token']);

    $branch = Branch::factory()->create([
        'esb_branch_code' => 'BPL',
        'esb_comcode' => 'TESTCO',
    ]);

    $report = SalesReport::create([
        'branch_id' => $branch->id,
        'report_date' => '2026-08-04',
        'submitted_at' => now(),
        'status' => SalesReportStatus::PendingSupervisor->value,
    ]);
    SalesReportEntry::create([
        'sales_report_id' => $report->id,
        'shift_number' => 1,
        'payment_method_name' => 'CASH',
        'sales_store_amount' => 35_000,
    ]);
    SalesReportReconciliation::create([
        'sales_report_id' => $report->id,
        'payment_method_name' => 'CASH',
        'reported_store_amount' => 35_000,
        'store_amount' => 35_000,
        'system_amount' => 35_000,
    ]);

    Http::fake(['*' => Http::response([[
        'salesNum' => 'SBPL1',
        'salesDateOut' => '2026-08-04 09:49:26',
        'grandTotal' => 35_000,
        'salesPayments' => [[
            'paymentMethodName' => 'CASH',
            'paymentMethodTypeName' => 'Cash',
            'paymentAmount' => 35_000,
        ]],
    ]], 200, ['X-Pagination-Page-Count' => '1'])]);

    $this->artisan('sales-reports:audit-cash-discrepancy', [
        '--from' => '2026-08-04',
        '--to' => '2026-08-04',
    ])
        ->expectsOutputToContain('Tidak ditemukan selisih')
        ->assertSuccessful();
});

it('applies the correction to system_amount and records a note when --apply is passed', function () {
    config()->set(['esb.tokens.TESTCO' => 'branch-token']);

    $branch = Branch::factory()->create([
        'name' => 'Bloomery Pabelan',
        'esb_branch_code' => 'BPL',
        'esb_comcode' => 'TESTCO',
    ]);

    $report = SalesReport::create([
        'branch_id' => $branch->id,
        'report_date' => '2026-08-04',
        'submitted_at' => now(),
        'status' => SalesReportStatus::PendingSupervisor->value,
    ]);
    $entry = SalesReportEntry::create([
        'sales_report_id' => $report->id,
        'shift_number' => 1,
        'payment_method_name' => 'CASH',
        // What staff actually counted in the drawer — untouched by the correction,
        // so the real (small) discrepancy against the corrected system total stays visible.
        'sales_store_amount' => 74_500,
    ]);
    $reconciliation = SalesReportReconciliation::create([
        'sales_report_id' => $report->id,
        'payment_method_name' => 'CASH',
        'reported_store_amount' => 74_500,
        'store_amount' => 74_500,
        'system_amount' => 100_000,
    ]);

    Http::fake(['*' => Http::response([[
        'salesNum' => 'SBPL1',
        'salesDateOut' => '2026-08-04 09:49:26',
        'grandTotal' => 35_000,
        'salesPayments' => [[
            'paymentMethodName' => 'CASH',
            'paymentMethodTypeName' => 'Cash',
            'paymentAmount' => 50_000,
        ]],
    ], [
        'salesNum' => 'SBPL2',
        'salesDateOut' => '2026-08-04 14:51:06',
        'grandTotal' => 39_000,
        'salesPayments' => [[
            'paymentMethodName' => 'CASH',
            'paymentMethodTypeName' => 'Cash',
            'paymentAmount' => 50_000,
        ]],
    ]], 200, ['X-Pagination-Page-Count' => '1'])]);

    $this->artisan('sales-reports:audit-cash-discrepancy', [
        '--report' => $report->id,
        '--apply' => true,
    ])
        ->expectsOutputToContain('berhasil dikoreksi')
        ->assertSuccessful();

    $fresh = $reconciliation->fresh();
    expect((float) $fresh->system_amount)->toBe(74_000.0)
        ->and((float) $entry->fresh()->sales_store_amount)->toBe(74_500.0)
        ->and($fresh->supervisor_notes)->toContain('Koreksi otomatis')
        ->and($fresh->supervisor_notes)->toContain('Rp100.000')
        ->and($fresh->supervisor_notes)->toContain('Rp74.000');
});
