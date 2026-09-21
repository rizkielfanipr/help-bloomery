<?php

use App\Actions\ExportSalesReportsXlsxAction;
use App\Enums\SalesReportStatus;
use App\Filament\Helpdesk\Resources\SalesReports\Pages\ListSalesReports;
use App\Filament\Helpdesk\Resources\SalesReports\SalesReportResource;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\SalesReport;
use App\Models\SalesReportEmployee;
use App\Models\SalesReportEntry;
use App\Models\SalesReportReconciliation;
use App\Models\SalesReportShiftSubmission;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

function salesReportExportRows(BinaryFileResponse $response): array
{
    $reader = new Reader;
    $reader->open($response->getFile()->getPathname());

    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }
    $reader->close();

    return $rows;
}

function exportableSalesReport(Branch $branch, string $date, SalesReportStatus $status = SalesReportStatus::Completed, array $attributes = []): SalesReport
{
    $report = SalesReport::create([
        'branch_id' => $branch->id,
        'report_date' => $date,
        'submitted_at' => "{$date} 21:00:00",
        'status' => $status->value,
        ...$attributes,
    ]);
    SalesReportEntry::create([
        'sales_report_id' => $report->id,
        'shift_number' => 1,
        'payment_method_name' => 'QRIS',
        'sales_store_amount' => 1_000_000,
    ]);
    SalesReportReconciliation::create([
        'sales_report_id' => $report->id,
        'payment_method_name' => 'QRIS',
        'reported_store_amount' => 1_000_000,
        'store_amount' => 1_000_000,
        'system_amount' => 990_000,
        'settlement_amount' => 985_000,
        'system_fetched_at' => now(),
    ]);

    return $report;
}

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->branch = Branch::factory()->create(['name' => 'Kitchen Jateng']);
    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole('SUPERADMIN');
    $this->actingAs($this->admin);
});

it('exports sales reports to xlsx with all data and proper formatting', function () {
    $submitter = User::factory()->create(['name' => 'Kasir Shift Satu']);
    $supervisor = User::factory()->create(['name' => 'Supervisor Toko']);
    $finance = User::factory()->create(['name' => 'Finance Pusat']);
    $employee = Employee::factory()->create(['branch_id' => $this->branch->id]);

    $report = exportableSalesReport($this->branch, '2026-09-10', SalesReportStatus::Completed, [
        'supervisor_reviewed_by' => $supervisor->id,
        'supervisor_reviewed_at' => '2026-09-11 09:00:00',
        'supervisor_note' => 'Sesuai.',
        'finance_reviewed_by' => $finance->id,
        'finance_reviewed_at' => '2026-09-12 10:30:00',
        'finance_note' => '=HYPERLINK("http://x")',
    ]);
    SalesReportShiftSubmission::create([
        'sales_report_id' => $report->id,
        'shift_number' => 2,
        'submitted_by' => $submitter->id,
        'submitted_at' => now(),
    ]);
    SalesReportShiftSubmission::create([
        'sales_report_id' => $report->id,
        'shift_number' => 1,
        'submitted_by' => $submitter->id,
        'submitted_at' => now(),
    ]);
    SalesReportEmployee::create([
        'sales_report_id' => $report->id,
        'shift_number' => 1,
        'employee_id' => $employee->id,
        'employee_code' => 'EMP-001',
        'employee_name' => '=SUM(1+1)',
        'employee_position' => 'Cashier',
    ]);

    $response = app(ExportSalesReportsXlsxAction::class)->execute(SalesReport::query());

    expect($response)->toBeInstanceOf(BinaryFileResponse::class)
        ->and($response->getFile()->getPathname())->toStartWith(realpath(sys_get_temp_dir()))
        ->and($response->getFile()->getFilename())->toStartWith('sales_reports_');

    $rows = salesReportExportRows($response);

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toContain('ID', 'Cabang', 'Tanggal', 'Sales System', 'Sales Store', 'Settlement', 'Status', 'Reviewer Finance', 'Catatan Finance');

    $data = array_combine($rows[0], $rows[1]);
    expect($data['ID'])->toBe($report->id)
        ->and($data['Cabang'])->toBe('Kitchen Jateng')
        ->and($data['Tanggal'])->toBe('2026-09-10')
        ->and($data['Shift Terkirim'])->toBe('Shift 1, Shift 2')
        ->and($data['Disubmit Oleh'])->toBe('Kasir Shift Satu')
        ->and($data['Sales System'])->toBe(990000)
        ->and($data['Sales Store'])->toBe(1000000)
        ->and($data['Settlement'])->toBe(985000)
        ->and($data['ID Employee'])->toBe('EMP-001')
        ->and($data['Staff In Charge'])->toBe("'=SUM(1+1)")
        ->and($data['Posisi Employee'])->toBe('Cashier')
        ->and($data['Status'])->toBe('Completed')
        ->and($data['Reviewer SPV'])->toBe('Supervisor Toko')
        ->and($data['Waktu Approval SPV'])->toBe('2026-09-11 09:00:00')
        ->and($data['Catatan SPV'])->toBe('Sesuai.')
        ->and($data['Reviewer Finance'])->toBe('Finance Pusat')
        ->and($data['Waktu Approval Finance'])->toBe('2026-09-12 10:30:00')
        ->and($data['Catatan Finance'])->toBe("'=HYPERLINK(\"http://x\")");
});

it('filters sales reports by an inclusive report date range in the direct xlsx export', function () {
    exportableSalesReport($this->branch, '2026-08-31');
    $first = exportableSalesReport($this->branch, '2026-09-01');
    $last = exportableSalesReport($this->branch, '2026-09-30');
    exportableSalesReport($this->branch, '2026-10-01');

    $response = app(ExportSalesReportsXlsxAction::class)->execute(SalesReport::query(), '2026-09-01', '2026-09-30');
    $rows = salesReportExportRows($response);

    expect($rows)->toHaveCount(3)
        ->and(collect($rows)->skip(1)->pluck(0)->all())->toBe([$first->id, $last->id])
        ->and($response->getFile()->getFilename())->toStartWith('sales_reports_');
});

it('only exports the sales reports of branches the user can access', function () {
    $otherBranch = Branch::factory()->create(['name' => 'Store Bandung']);
    $mine = exportableSalesReport($this->branch, '2026-09-10');
    exportableSalesReport($otherBranch, '2026-09-10');

    $finance = User::factory()->create(['is_active' => true]);
    $finance->assignRole('FINANCE_STAFF');
    $finance->syncBranchAccess([$this->branch->id], $this->branch->id);
    $this->actingAs($finance);

    $rows = salesReportExportRows(app(ExportSalesReportsXlsxAction::class)->execute(SalesReportResource::getEloquentQuery()));

    expect($rows)->toHaveCount(2)
        ->and($rows[1][0])->toBe($mine->id)
        ->and($rows[1][1])->toBe('Kitchen Jateng');
});

it('downloads the xlsx from the export button on the sales report list', function () {
    exportableSalesReport($this->branch, '2026-09-10');

    Livewire::test(ListSalesReports::class)
        ->callAction(TestAction::make('export_sales_reports')->table(), ['date_from' => '2026-09-01', 'date_until' => '2026-09-30'])
        ->assertHasNoActionErrors()
        ->assertFileDownloaded('sales-report-2026-09-01-sampai-2026-09-30.xlsx');

    Livewire::test(ListSalesReports::class)
        ->callAction(TestAction::make('export_sales_reports')->table(), ['date_from' => '2026-09-30', 'date_until' => '2026-09-01'])
        ->assertHasActionErrors(['date_until']);
});
