<?php

use App\Enums\SalesReportStatus;
use App\Filament\Casual\Pages\SalesReportShiftPage;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\SalesReport;
use App\Models\SalesReportEntry;
use App\Models\User;
use App\Services\EsbService;
use App\Services\SalesReportReconciliationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('groups payment summaries by label, ordered DINE IN before TAKEAWAY, and excludes NO LABEL', function () {
    config()->set([
        'esb.base_url' => 'https://sales-esb.test',
        'esb.tokens.DINEIN_CODE' => 'token-dinein',
        'esb.tokens.TAKEAWAY_CODE' => 'token-takeaway',
        'esb.tokens.NOLABEL_CODE' => 'token-nolabel',
    ]);

    $branch = Branch::factory()->create();
    $branch->esbCodes()->create(['esb_branch_code' => 'BPL', 'esb_comcode' => 'TAKEAWAY_CODE', 'label' => 'TAKEAWAY']);
    $branch->esbCodes()->create(['esb_branch_code' => 'BPL', 'esb_comcode' => 'DINEIN_CODE', 'label' => 'DINE IN']);
    $branch->esbCodes()->create(['esb_branch_code' => 'BPL', 'esb_comcode' => 'NOLABEL_CODE', 'label' => 'NO LABEL']);
    $branch->load('esbCodes');

    Http::fake(function (Request $request) {
        $auth = (string) $request->header('Authorization')[0];
        $amount = str_contains($auth, 'token-dinein') ? 30000 : (str_contains($auth, 'token-takeaway') ? 70000 : 999999);

        return Http::response([[
            'salesNum' => 'A1',
            'salesDateOut' => '2026-08-01 10:00:00',
            'grandTotal' => $amount,
            'salesPayments' => [[
                'paymentMethodName' => 'CASH',
                'paymentMethodTypeName' => 'Cash',
                'paymentAmount' => $amount,
            ]],
        ]], 200, ['X-Pagination-Page-Count' => '1']);
    });

    $groups = (new EsbService)->getPaymentSummaryByLabelForBranch($branch, '2026-08-01');

    expect(array_keys($groups))->toBe(['DINE IN', 'TAKEAWAY'])
        ->and($groups['DINE IN']['ok'])->toBeTrue()
        ->and($groups['DINE IN']['rows'][0]['total'])->toBe(30000.0)
        ->and($groups['TAKEAWAY']['ok'])->toBeTrue()
        ->and($groups['TAKEAWAY']['rows'][0]['total'])->toBe(70000.0);
});

it('returns no groups when a branch only has a NO LABEL ESB code pair', function () {
    config()->set(['esb.tokens.NOLABEL_CODE' => 'token-nolabel']);

    $branch = Branch::factory()->create();
    $branch->esbCodes()->create(['esb_branch_code' => 'BPL', 'esb_comcode' => 'NOLABEL_CODE', 'label' => 'NO LABEL']);
    $branch->load('esbCodes');

    $groups = (new EsbService)->getPaymentSummaryByLabelForBranch($branch, '2026-08-01');

    expect($groups)->toBe([]);
});

it('merges reconciliation rows separately per label for the same payment method', function () {
    config()->set([
        'esb.base_url' => 'https://sales-esb.test',
        'esb.tokens.DINEIN_CODE' => 'token-dinein',
        'esb.tokens.TAKEAWAY_CODE' => 'token-takeaway',
    ]);

    $branch = Branch::factory()->create();
    $branch->esbCodes()->create(['esb_branch_code' => 'BPL', 'esb_comcode' => 'DINEIN_CODE', 'label' => 'DINE IN']);
    $branch->esbCodes()->create(['esb_branch_code' => 'BPL', 'esb_comcode' => 'TAKEAWAY_CODE', 'label' => 'TAKEAWAY']);
    $branch->load('esbCodes');

    $report = SalesReport::create([
        'branch_id' => $branch->id,
        'report_date' => '2026-08-01',
        'status' => SalesReportStatus::Draft->value,
    ]);
    SalesReportEntry::create([
        'sales_report_id' => $report->id,
        'shift_number' => 1,
        'label' => 'DINE IN',
        'payment_method_name' => 'CASH',
        'sales_store_amount' => 25000,
    ]);
    SalesReportEntry::create([
        'sales_report_id' => $report->id,
        'shift_number' => 1,
        'label' => 'TAKEAWAY',
        'payment_method_name' => 'CASH',
        'sales_store_amount' => 65000,
    ]);

    Http::fake(function (Request $request) {
        $auth = (string) $request->header('Authorization')[0];
        $amount = str_contains($auth, 'token-dinein') ? 30000 : 70000;

        return Http::response([[
            'salesNum' => 'A1',
            'salesDateOut' => '2026-08-01 10:00:00',
            'grandTotal' => $amount,
            'salesPayments' => [[
                'paymentMethodName' => 'CASH',
                'paymentMethodTypeName' => 'Cash',
                'paymentAmount' => $amount,
            ]],
        ]], 200, ['X-Pagination-Page-Count' => '1']);
    });

    $ok = app(SalesReportReconciliationService::class)->reconcile($report->fresh());

    expect($ok)->toBeTrue();

    $report->load('reconciliations');
    $dineIn = $report->reconciliations->firstWhere('label', 'DINE IN');
    $takeaway = $report->reconciliations->firstWhere('label', 'TAKEAWAY');

    expect($report->reconciliations)->toHaveCount(2)
        ->and((float) $dineIn->reported_store_amount)->toBe(25000.0)
        ->and((float) $dineIn->system_amount)->toBe(30000.0)
        ->and((float) $takeaway->reported_store_amount)->toBe(65000.0)
        ->and((float) $takeaway->system_amount)->toBe(70000.0);
});

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->branch = Branch::factory()->create();
    $this->staff = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
    $this->staff->assignRole('CASUAL_STAFF');
    Employee::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
});

it('builds shift input rows grouped by label, DINE IN before TAKEAWAY', function () {
    config()->set([
        'esb.base_url' => 'https://sales-esb.test',
        'esb.tokens.DINEIN_CODE' => 'token-dinein',
        'esb.tokens.TAKEAWAY_CODE' => 'token-takeaway',
    ]);

    $this->branch->esbCodes()->create(['esb_branch_code' => 'BPL', 'esb_comcode' => 'TAKEAWAY_CODE', 'label' => 'TAKEAWAY']);
    $this->branch->esbCodes()->create(['esb_branch_code' => 'BPL', 'esb_comcode' => 'DINEIN_CODE', 'label' => 'DINE IN']);

    Http::fake(function (Request $request) {
        $auth = (string) $request->header('Authorization')[0];
        $amount = str_contains($auth, 'token-dinein') ? 30000 : 70000;

        return Http::response([[
            'salesNum' => 'A1',
            'salesDateOut' => '2026-08-01 10:00:00',
            'grandTotal' => $amount,
            'salesPayments' => [[
                'paymentMethodName' => 'CASH',
                'paymentMethodTypeName' => 'Cash',
                'paymentAmount' => $amount,
            ]],
        ]], 200, ['X-Pagination-Page-Count' => '1']);
    });

    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->staff);

    $page = Livewire::test(SalesReportShiftPage::class, ['reportDate' => today()->toDateString()])
        ->call('fetchFromEsb')
        ->assertSet('esbFetched', true);

    $rows = $page->get('rows');

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['label'])->toBe('DINE IN')
        ->and($rows[1]['label'])->toBe('TAKEAWAY');
});

it('warns and does not load rows when the branch only has a NO LABEL ESB pair', function () {
    config()->set(['esb.tokens.NOLABEL_CODE' => 'token-nolabel']);
    $this->branch->esbCodes()->create(['esb_branch_code' => 'BPL', 'esb_comcode' => 'NOLABEL_CODE', 'label' => 'NO LABEL']);

    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->staff);

    Livewire::test(SalesReportShiftPage::class, ['reportDate' => today()->toDateString()])
        ->call('fetchFromEsb')
        ->assertSet('esbFetched', false)
        ->assertSet('rows', [])
        ->assertNotified();
});

it('persists each row with its own label when a shift is submitted', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->staff);
    $employee = Employee::where('branch_id', $this->branch->id)->first();

    Livewire::test(SalesReportShiftPage::class, ['reportDate' => today()->toDateString()])
        ->set('esbFetched', true)
        ->set('rows', [
            ['label' => 'DINE IN', 'name' => 'CASH', 'sales_store' => '30000', 'notes' => ''],
            ['label' => 'TAKEAWAY', 'name' => 'CASH', 'sales_store' => '70000', 'notes' => ''],
        ])
        ->set('employeeIds', [$employee->id])
        ->call('requestConfirm')
        ->assertHasNoErrors()
        ->call('save')
        ->assertHasNoErrors();

    $report = SalesReport::where('branch_id', $this->branch->id)->whereDate('report_date', today())->firstOrFail();

    expect($report->entries)->toHaveCount(2)
        ->and($report->entries->firstWhere('label', 'DINE IN')?->sales_store_amount)->toEqual(30000)
        ->and($report->entries->firstWhere('label', 'TAKEAWAY')?->sales_store_amount)->toEqual(70000);
});
