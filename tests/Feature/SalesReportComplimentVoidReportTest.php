<?php

use App\Actions\SalesReport\ExportSalesReportComplimentsXlsxAction;
use App\Actions\SalesReport\ExportSalesReportVoidsXlsxAction;
use App\Enums\SalesReportStatus;
use App\Filament\Helpdesk\Resources\SalesReportCompliments\Pages\ListSalesReportCompliments;
use App\Filament\Helpdesk\Resources\SalesReportCompliments\SalesReportComplimentResource;
use App\Filament\Helpdesk\Resources\SalesReports\Pages\ListSalesReports;
use App\Filament\Helpdesk\Resources\SalesReportVoids\Pages\ListSalesReportVoids;
use App\Filament\Helpdesk\Resources\SalesReportVoids\SalesReportVoidResource;
use App\Models\Branch;
use App\Models\ComplimentType;
use App\Models\SalesReport;
use App\Models\SalesReportCompliment;
use App\Models\SalesReportVoid;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->branchA = Branch::factory()->create(['name' => 'Branch Alpha']);
    $this->branchB = Branch::factory()->create(['name' => 'Branch Beta']);
    $this->reportA = SalesReport::factory()->create(['branch_id' => $this->branchA->id, 'report_date' => '2026-10-10', 'status' => SalesReportStatus::Completed]);
    $this->reportB = SalesReport::factory()->create(['branch_id' => $this->branchB->id, 'report_date' => '2026-10-12', 'status' => SalesReportStatus::Completed]);
    $this->finance = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $this->finance->assignRole('FINANCE_STAFF');
});

/** @return list<list<mixed>> */
function reportExportRows(BinaryFileResponse $response): array
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

it('shows Laporan Harian, Compliment, and Void as one Sales Report sub-navigation', function () {
    $this->actingAs($this->finance);

    foreach ([ListSalesReports::class, ListSalesReportCompliments::class, ListSalesReportVoids::class] as $page) {
        Livewire::test($page)->assertSuccessful()->assertSee('Laporan Harian')->assertSee('Compliment')->assertSee('Void');
    }

    $viewer = User::factory()->create(['is_active' => true]);
    $this->actingAs($viewer)->get(SalesReportComplimentResource::getUrl('index'))->assertForbidden();
    $this->get(SalesReportVoidResource::getUrl('index'))->assertForbidden();
});

it('limits the Compliment and Void reports to the branches the user may access', function () {
    $complimentA = SalesReportCompliment::factory()->create(['sales_report_id' => $this->reportA->id]);
    $complimentB = SalesReportCompliment::factory()->create(['sales_report_id' => $this->reportB->id]);
    $voidA = SalesReportVoid::factory()->create(['sales_report_id' => $this->reportA->id]);
    $voidB = SalesReportVoid::factory()->create(['sales_report_id' => $this->reportB->id]);
    $supervisor = User::factory()->create(['is_active' => true, 'branch_id' => $this->branchA->id]);
    $supervisor->assignRole('SUPERVISOR_STORE');

    $this->actingAs($supervisor);
    Livewire::test(ListSalesReportCompliments::class)->assertCanSeeTableRecords([$complimentA])->assertCanNotSeeTableRecords([$complimentB]);
    Livewire::test(ListSalesReportVoids::class)->assertCanSeeTableRecords([$voidA])->assertCanNotSeeTableRecords([$voidB]);

    $rows = reportExportRows(app(ExportSalesReportComplimentsXlsxAction::class)->execute(SalesReportComplimentResource::getEloquentQuery()));
    expect($rows)->toHaveCount(2)->and($rows[1][0])->toBe($complimentA->id);
});

it('filters Compliments by date, branch, shift, type, Sales Number, and source', function () {
    $type = ComplimentType::factory()->create(['name' => 'Compliment Training']);
    $verified = SalesReportCompliment::factory()->create(['sales_report_id' => $this->reportA->id, 'sales_number' => '0001234', 'shift_number' => 1]);
    $manual = SalesReportCompliment::factory()->manual()->create(['sales_report_id' => $this->reportA->id, 'sales_number' => 'M-9', 'shift_number' => 2, 'compliment_type_id' => $type->id]);
    $legacy = SalesReportCompliment::factory()->legacy()->create(['sales_report_id' => $this->reportB->id]);

    $this->actingAs($this->finance);
    Livewire::test(ListSalesReportCompliments::class)
        ->assertCanSeeTableRecords([$verified, $manual, $legacy])
        ->filterTable('report_date', ['from' => '2026-10-11', 'until' => '2026-10-31'])
        ->assertCanSeeTableRecords([$legacy])->assertCanNotSeeTableRecords([$verified, $manual])
        ->resetTableFilters()
        ->filterTable('branch', $this->branchA->id)
        ->assertCanSeeTableRecords([$verified, $manual])->assertCanNotSeeTableRecords([$legacy])
        ->resetTableFilters()
        ->filterTable('shift_number', 2)
        ->assertCanSeeTableRecords([$manual])->assertCanNotSeeTableRecords([$verified])
        ->resetTableFilters()
        ->filterTable('compliment_type_id', $type->id)
        ->assertCanSeeTableRecords([$manual])->assertCanNotSeeTableRecords([$verified, $legacy])
        ->resetTableFilters()
        ->filterTable('sales_number', ['value' => '0001'])
        ->assertCanSeeTableRecords([$verified])->assertCanNotSeeTableRecords([$manual, $legacy])
        ->resetTableFilters()
        ->filterTable('source', 'legacy')
        ->assertCanSeeTableRecords([$legacy])->assertCanNotSeeTableRecords([$verified, $manual])
        ->resetTableFilters()
        ->filterTable('source', 'manual')
        ->assertCanSeeTableRecords([$manual])->assertCanNotSeeTableRecords([$verified, $legacy])
        ->assertSee('Manual (Unverified)');
});

it('filters Voids by payment method and Sales Number and opens the detail', function () {
    $cash = SalesReportVoid::factory()->create(['sales_report_id' => $this->reportA->id, 'payment_method_name' => 'CASH', 'sales_number' => 'V-1']);
    $qris = SalesReportVoid::factory()->create(['sales_report_id' => $this->reportA->id, 'payment_method_name' => 'QRIS', 'sales_number' => 'V-2']);

    $this->actingAs($this->finance);
    Livewire::test(ListSalesReportVoids::class)
        ->filterTable('payment_method', ['value' => 'qris'])
        ->assertCanSeeTableRecords([$qris])->assertCanNotSeeTableRecords([$cash])
        ->resetTableFilters()
        ->filterTable('sales_number', ['value' => 'V-1'])
        ->assertCanSeeTableRecords([$cash])->assertCanNotSeeTableRecords([$qris])
        ->mountTableAction('detail', $cash)
        ->assertMountedActionModalSee(['Foto Nota Awal', 'Foto Nota Setelah Void', 'Buka Sales Report asal']);
});

it('exports one row per Compliment with the leading zero, sanitized text, and legacy rows without invented values', function () {
    $verified = SalesReportCompliment::factory()->create(['sales_report_id' => $this->reportA->id, 'sales_number' => '0001234', 'notes' => '=HYPERLINK("x")']);
    $legacy = SalesReportCompliment::factory()->legacy()->create(['sales_report_id' => $this->reportB->id]);

    $rows = reportExportRows(app(ExportSalesReportComplimentsXlsxAction::class)->execute(SalesReportCompliment::query(), '2026-10-01', '2026-10-31'));

    expect($rows[0])->toBe(ExportSalesReportComplimentsXlsxAction::HEADERS)
        ->and($rows)->toHaveCount(3)
        ->and($rows[1][0])->toBe($verified->id)
        ->and($rows[1][7])->toBe('0001234')
        ->and($rows[1][8])->toBe('Verified ESB')
        ->and($rows[1][11])->toBe('\'=HYPERLINK("x")')
        ->and($rows[1][16])->toBe('Completed')
        ->and($rows[2][0])->toBe($legacy->id)
        ->and($rows[2][7])->toBe('')
        ->and($rows[2][8])->toBe('Legacy')
        ->and($rows[2][3])->toBe('')
        ->and(collect($rows)->flatten()->filter(fn ($cell): bool => is_string($cell) && str_contains($cell, 'http'))->all())->toBe([]);
});

it('exports one row per Void, follows the page filters, and yields a header-only file when empty', function () {
    $void = SalesReportVoid::factory()->create(['sales_report_id' => $this->reportA->id, 'sales_number' => '00777', 'reason' => '+cmd', 'transaction_amount' => 50000]);
    SalesReportVoid::factory()->create(['sales_report_id' => $this->reportB->id]);

    $this->actingAs($this->finance);
    $page = Livewire::test(ListSalesReportVoids::class)->filterTable('branch', $this->branchA->id);
    $rows = reportExportRows(app(ExportSalesReportVoidsXlsxAction::class)->execute($page->instance()->getFilteredTableQuery()));

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toBe(ExportSalesReportVoidsXlsxAction::HEADERS)
        ->and($rows[1][0])->toBe($void->id)
        ->and($rows[1][7])->toBe('00777')
        ->and($rows[1][10])->toEqual(50000)
        ->and($rows[1][12])->toBe("'+cmd")
        ->and($rows[1][13])->toBe('Ya');

    $empty = reportExportRows(app(ExportSalesReportVoidsXlsxAction::class)->execute(SalesReportVoid::query()->whereRaw('1 = 0')));
    expect($empty)->toBe([ExportSalesReportVoidsXlsxAction::HEADERS]);

    $page->callTableAction('export')->assertFileDownloaded('void-semua-sampai-semua.xlsx');
});

it('exports more than one chunk without losing or repeating rows, regardless of the table sort', function () {
    $now = now();
    DB::table('sales_report_compliments')->insert(collect(range(1, 520))->map(fn (int $i): array => [
        'sales_report_id' => $this->reportA->id, 'shift_number' => 1, 'sales_number' => sprintf('%07d', $i), 'sales_number_source' => 'manual',
        'compliment_type_name' => 'Compliment Owner', 'attachment_paths' => '[]', 'notes' => 'Bulk', 'created_at' => $now, 'updated_at' => $now,
    ])->all());

    $rows = reportExportRows(app(ExportSalesReportComplimentsXlsxAction::class)->execute(SalesReportCompliment::query()->orderByDesc('created_at')));

    expect($rows)->toHaveCount(521)
        ->and(collect($rows)->skip(1)->pluck(0)->unique()->count())->toBe(520)
        ->and($rows[1][7])->toBe('0000001');
});
