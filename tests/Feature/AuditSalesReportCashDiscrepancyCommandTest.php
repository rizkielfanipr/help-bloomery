<?php

use App\Enums\SalesReportStatus;
use App\Models\Branch;
use App\Models\SalesReport;
use App\Models\SalesReportEntry;
use App\Models\User;
use Illuminate\Support\Facades\Http;

it('flags submitted reports whose stored CASH total no longer matches ESB, without changing any data', function () {
    config()->set(['esb.tokens.TESTCO' => 'branch-token']);

    $branch = Branch::factory()->create([
        'name' => 'Bloomery Pabelan',
        'esb_branch_code' => 'BPL',
        'esb_comcode' => 'TESTCO',
    ]);
    $submitter = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);

    $report = SalesReport::create([
        'branch_id' => $branch->id,
        'submitted_by' => $submitter->id,
        'report_date' => '2026-08-04',
        'shift_number' => 1,
        'shift_started_at' => '2026-08-04 09:00:00',
        'shift_ended_at' => '2026-08-04 15:00:00',
        'submitted_at' => now(),
        'status' => SalesReportStatus::PendingSupervisor->value,
    ]);
    $entry = SalesReportEntry::create([
        'sales_report_id' => $report->id,
        'payment_method_name' => 'CASH',
        'sales_system_amount' => 100_000,
        'sales_store_amount' => 100_000,
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

    expect($entry->fresh()->sales_system_amount)->toEqual(100_000);
});

it('reports no discrepancy when the stored CASH total already matches ESB', function () {
    config()->set(['esb.tokens.TESTCO' => 'branch-token']);

    $branch = Branch::factory()->create([
        'esb_branch_code' => 'BPL',
        'esb_comcode' => 'TESTCO',
    ]);
    $submitter = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);

    $report = SalesReport::create([
        'branch_id' => $branch->id,
        'submitted_by' => $submitter->id,
        'report_date' => '2026-08-04',
        'shift_number' => 1,
        'shift_started_at' => '2026-08-04 09:00:00',
        'shift_ended_at' => '2026-08-04 15:00:00',
        'submitted_at' => now(),
        'status' => SalesReportStatus::PendingSupervisor->value,
    ]);
    SalesReportEntry::create([
        'sales_report_id' => $report->id,
        'payment_method_name' => 'CASH',
        'sales_system_amount' => 35_000,
        'sales_store_amount' => 35_000,
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
