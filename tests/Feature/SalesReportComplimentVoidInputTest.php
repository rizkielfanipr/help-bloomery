<?php

use App\Actions\CalculateBasketSizeAction;
use App\Enums\SalesReportDataSource;
use App\Enums\SalesReportStatus;
use App\Filament\Casual\Pages\SalesReportShiftPage;
use App\Models\Branch;
use App\Models\ComplimentType;
use App\Models\Employee;
use App\Models\SalesReport;
use App\Models\SalesReportCompliment;
use App\Models\SalesReportVoid;
use App\Models\User;
use Database\Seeders\ComplimentTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(ComplimentTypeSeeder::class);
    Storage::fake('b2');
    Filament::setCurrentPanel(Filament::getPanel('casual'));

    config()->set(['esb.base_url' => 'https://sales-esb.test', 'esb.tokens.TAKEAWAY_CODE' => 'token-takeaway']);
    $this->branch = Branch::factory()->create(['sales_shift_count' => 2]);
    $this->branch->esbCodes()->create(['esb_branch_code' => 'BPL', 'esb_comcode' => 'TAKEAWAY_CODE', 'label' => 'TAKEAWAY']);
    $this->staff = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
    $this->staff->assignRole('CASUAL_STAFF');
    $this->employee = Employee::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
    $this->owner = ComplimentType::where('name', 'Compliment Owner')->sole();

    // Shift 1 (07:00–15:00) has two transactions; 9999 belongs to shift 2 and must never verify.
    Http::fake(fn () => Http::response([
        ['salesNum' => '0001234', 'salesDateOut' => today()->setTime(10, 0)->format('Y-m-d H:i:s'), 'grandTotal' => 75000,
            'salesPayments' => [['paymentMethodName' => 'QRIS', 'paymentMethodTypeName' => 'E-Wallet', 'paymentAmount' => 75000]]],
        ['salesNum' => 'B-200', 'salesDateOut' => today()->setTime(11, 0)->format('Y-m-d H:i:s'), 'grandTotal' => 50000,
            'salesPayments' => [['paymentMethodName' => 'CASH', 'paymentMethodTypeName' => 'Cash', 'paymentAmount' => 50000]]],
        ['salesNum' => '9999', 'salesDateOut' => today()->setTime(16, 0)->format('Y-m-d H:i:s'), 'grandTotal' => 20000,
            'salesPayments' => [['paymentMethodName' => 'CASH', 'paymentMethodTypeName' => 'Cash', 'paymentAmount' => 20000]]],
    ], 200, ['X-Pagination-Page-Count' => '1']));

    $this->actingAs($this->staff);
});

function cvShiftPage(): Testable
{
    return Livewire::test(SalesReportShiftPage::class, ['reportDate' => today()->toDateString(), 'shiftNumber' => 1])
        ->call('fetchFromEsb')
        ->assertSet('esbFetched', true)
        ->set('employeeIds', [test()->employee->id]);
}

function cvCompliment(array $overrides = []): array
{
    return ['compliment_type_id' => test()->owner->id, 'sales_number' => '0001234', 'sales_number_source' => 'esb_snapshot', 'notes' => 'Compliment tamu owner.', 'attachments' => [], ...$overrides];
}

function cvVoid(array $overrides = []): array
{
    return ['sales_number' => 'B-200', 'sales_number_source' => 'esb_snapshot', 'payment_method_name' => 'CASH', 'payment_method_source' => 'esb_snapshot',
        'reason' => 'Salah input menu.', 'original_receipt' => null, 'voided_receipt' => null, ...$overrides];
}

function cvSubmittedReport(): SalesReport
{
    return SalesReport::whereBelongsTo(test()->branch)->whereDate('report_date', today())->sole();
}

it('submits a shift without Compliment or Void', function () {
    cvShiftPage()->call('requestConfirm')->assertHasNoErrors()->call('save')->assertHasNoErrors();

    expect(cvSubmittedReport()->compliments()->count())->toBe(0)
        ->and(cvSubmittedReport()->voids()->count())->toBe(0);
});

it('keeps the ESB transaction snapshot on the server and searches it server-side', function () {
    $page = cvShiftPage();

    expect($page->instance())->not->toHaveProperty('transactionSnapshots');
    $page->assertDontSeeHtml('0001234');

    expect(collect($page->instance()->searchSalesNumbers(''))->pluck('sales_number')->all())->toBe(['0001234', 'B-200'])
        ->and(collect($page->instance()->searchSalesNumbers('b-2'))->pluck('sales_number')->all())->toBe(['B-200']);
});

it('stores a verified Compliment with its ESB context and keeps the leading zero', function () {
    cvShiftPage()
        ->set('compliments', [cvCompliment()])
        ->set('compliments.0.attachments', [UploadedFile::fake()->image('nota.jpg')])
        ->call('requestConfirm')->assertHasNoErrors()
        ->call('save')->assertHasNoErrors();

    $compliment = cvSubmittedReport()->compliments()->sole();
    expect($compliment->sales_number)->toBe('0001234')
        ->and($compliment->sales_number_source)->toBe(SalesReportDataSource::EsbSnapshot)
        ->and($compliment->source_comcode)->toBe('TAKEAWAY_CODE')
        ->and($compliment->source_branch_code)->toBe('BPL')
        ->and($compliment->sales_transaction_at->format('H:i'))->toBe('10:00')
        ->and($compliment->compliment_type_name)->toBe('Compliment Owner')
        ->and($compliment->submitted_by)->toBe($this->staff->id);
    Storage::disk('b2')->assertExists($compliment->attachment_paths[0]);
});

it('stores a manual Sales Number as unverified, trimmed but otherwise unchanged', function () {
    cvShiftPage()
        ->call('addCompliment')
        ->set('compliments.0.compliment_type_id', $this->owner->id)
        ->call('setEntryMode', 'compliments', 0, 'sales_number', 'manual')
        ->assertSet('compliments.0.sales_number_source', 'manual')
        ->set('compliments.0.sales_number', '  00AB-77  ')
        ->set('compliments.0.notes', 'Nota manual.')
        ->set('compliments.0.attachments', [UploadedFile::fake()->image('nota.png')])
        ->call('requestConfirm')->assertHasNoErrors()
        ->call('save')->assertHasNoErrors();

    $compliment = cvSubmittedReport()->compliments()->sole();
    expect($compliment->sales_number)->toBe('00AB-77')
        ->and($compliment->sales_number_source)->toBe(SalesReportDataSource::Manual)
        ->and($compliment->source_comcode)->toBeNull();
});

it('refuses a verified Sales Number from another shift and writes nothing', function () {
    cvShiftPage()
        ->set('compliments', [cvCompliment(['sales_number' => '9999'])])
        ->set('compliments.0.attachments', [UploadedFile::fake()->image('nota.jpg')])
        ->call('requestConfirm')->assertHasNoErrors()
        ->call('save')
        ->assertHasErrors(['compliments.0.sales_number']);

    expect(SalesReport::query()->count())->toBe(0)
        ->and(Storage::disk('b2')->allFiles())->toBe([]);
});

it('requires every Compliment field and image-only evidence', function () {
    cvShiftPage()
        ->set('compliments', [['compliment_type_id' => null, 'sales_number' => '', 'sales_number_source' => 'esb_snapshot', 'notes' => '', 'attachments' => []]])
        ->call('requestConfirm')
        ->assertHasErrors(['compliments.0.compliment_type_id', 'compliments.0.sales_number', 'compliments.0.notes', 'compliments.0.attachments'])
        ->set('compliments', [cvCompliment()])
        ->set('compliments.0.attachments', [UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf')])
        ->call('requestConfirm')
        ->assertHasErrors(['compliments.0.attachments.0'])
        ->set('compliments.0.attachments', collect(range(1, 6))->map(fn (int $i) => UploadedFile::fake()->image("nota-{$i}.jpg"))->all())
        ->call('requestConfirm')
        ->assertHasErrors(['compliments.0.attachments'])
        ->set('compliments.0.attachments', [UploadedFile::fake()->image('big.jpg')->size(6000)])
        ->call('requestConfirm')
        ->assertHasErrors(['compliments.0.attachments.0']);
});

it('rejects an exact duplicate Compliment in the same submission', function () {
    cvShiftPage()
        ->set('compliments', [cvCompliment(), cvCompliment()])
        ->set('compliments.0.attachments', [UploadedFile::fake()->image('a.jpg')])
        ->set('compliments.1.attachments', [UploadedFile::fake()->image('b.jpg')])
        ->call('requestConfirm')->assertHasNoErrors()
        ->call('save')
        ->assertHasErrors(['compliments.1.sales_number']);

    expect(Storage::disk('b2')->allFiles())->toBe([]);
});

it('stores a verified Void with the payment snapshot and both receipts on their own fields', function () {
    cvShiftPage()
        ->call('addVoid')
        ->set('voids.0.sales_number', 'B-200')
        ->set('voids.0.payment_method_name', 'CASH')
        ->set('voids.0.reason', 'Customer batal pesan.')
        ->set('voids.0.original_receipt', UploadedFile::fake()->image('awal.jpg'))
        ->set('voids.0.voided_receipt', UploadedFile::fake()->image('void.png'))
        ->call('requestConfirm')->assertHasNoErrors()
        ->call('save')->assertHasNoErrors();

    $void = cvSubmittedReport()->voids()->sole();
    expect($void->sales_number)->toBe('B-200')
        ->and($void->sales_number_source)->toBe(SalesReportDataSource::EsbSnapshot)
        ->and((float) $void->transaction_amount)->toBe(50000.0)
        ->and($void->payment_method_name)->toBe('CASH')
        ->and($void->payment_method_source)->toBe(SalesReportDataSource::EsbSnapshot)
        ->and($void->reason)->toBe('Customer batal pesan.')
        ->and($void->original_receipt_path)->toEndWith('.jpg')
        ->and($void->voided_receipt_path)->toEndWith('.png')
        ->and($void->original_receipt_path)->not->toBe($void->voided_receipt_path);
    Storage::disk('b2')->assertExists([$void->original_receipt_path, $void->voided_receipt_path]);
});

it('requires every Void field and refuses a payment method outside the ESB list unless manual', function () {
    cvShiftPage()
        ->call('addVoid')
        ->call('requestConfirm')
        ->assertHasErrors(['voids.0.sales_number', 'voids.0.payment_method_name', 'voids.0.reason', 'voids.0.original_receipt', 'voids.0.voided_receipt'])
        ->set('voids', [cvVoid(['payment_method_name' => 'GOPAY'])])
        ->set('voids.0.original_receipt', UploadedFile::fake()->image('awal.jpg'))
        ->set('voids.0.voided_receipt', UploadedFile::fake()->create('void.pdf', 50, 'application/pdf'))
        ->call('requestConfirm')
        ->assertHasErrors(['voids.0.voided_receipt'])
        ->set('voids.0.voided_receipt', UploadedFile::fake()->image('void.jpg'))
        ->call('requestConfirm')->assertHasNoErrors()
        ->call('save')
        ->assertHasErrors(['voids.0.payment_method_name'])
        ->call('setEntryMode', 'voids', 0, 'payment_method', 'manual')
        ->set('voids.0.payment_method_name', 'GOPAY')
        ->call('requestConfirm')->assertHasNoErrors()
        ->call('save')->assertHasNoErrors();

    $void = cvSubmittedReport()->voids()->sole();
    expect($void->payment_method_name)->toBe('GOPAY')
        ->and($void->payment_method_source)->toBe(SalesReportDataSource::Manual);
});

it('removes every newly uploaded file when the database transaction fails', function () {
    $this->mock(CalculateBasketSizeAction::class)->shouldReceive('execute')->andThrow(new RuntimeException('database down'));

    expect(fn () => cvShiftPage()
        ->set('compliments', [cvCompliment()])
        ->set('compliments.0.attachments', [UploadedFile::fake()->image('nota.jpg')])
        ->set('voids', [cvVoid()])
        ->set('voids.0.original_receipt', UploadedFile::fake()->image('awal.jpg'))
        ->set('voids.0.voided_receipt', UploadedFile::fake()->image('void.jpg'))
        ->call('requestConfirm')
        ->call('save'))->toThrow(RuntimeException::class);

    expect(SalesReport::query()->count())->toBe(0)
        ->and(SalesReportCompliment::query()->count())->toBe(0)
        ->and(SalesReportVoid::query()->count())->toBe(0)
        ->and(Storage::disk('b2')->allFiles())->toBe([]);
});

it('shows submitted Compliment and Void read-only with source badges and legacy copy', function () {
    $report = SalesReport::factory()->create(['branch_id' => $this->branch->id, 'report_date' => today(), 'status' => SalesReportStatus::Draft]);
    $report->shiftSubmissions()->create(['shift_number' => 1, 'submitted_by' => $this->staff->id, 'submitted_at' => now()]);
    SalesReportCompliment::factory()->legacy()->create(['sales_report_id' => $report->id, 'compliment_type_name' => 'Compliment Owner']);
    SalesReportCompliment::factory()->manual()->create(['sales_report_id' => $report->id, 'sales_number' => 'M-01']);
    SalesReportVoid::factory()->create(['sales_report_id' => $report->id, 'sales_number' => '0007', 'payment_method_name' => 'QRIS']);

    Livewire::test(SalesReportShiftPage::class, ['reportDate' => today()->toDateString(), 'shiftNumber' => 1])
        ->assertSet('isSubmitted', true)
        ->assertSee('Data lama — Sales Number belum tersedia')
        ->assertSee('File lama (PDF) 1')
        ->assertSee('Manual (Unverified)')
        ->assertSee('Verified ESB')
        ->assertSee('0007')
        ->assertSee('Nota Awal')
        ->assertSee('Nota Setelah Void')
        ->assertDontSee('Tambah Compliment')
        ->assertDontSee('Tambah Void')
        ->assertDontSeeHtml('sales-report-compliments/legacy-nota.pdf');
});

it('serves evidence only to authorized users of the report branch', function () {
    $report = SalesReport::factory()->create(['branch_id' => $this->branch->id]);
    Storage::disk('b2')->put('sales-report-compliments/legacy-nota.pdf', 'pdf');
    Storage::disk('b2')->put('sales-report-voids/original.jpg', 'image');
    $compliment = SalesReportCompliment::factory()->legacy()->create(['sales_report_id' => $report->id]);
    $void = SalesReportVoid::factory()->create(['sales_report_id' => $report->id]);

    $this->get(route('sales-report-evidence.compliment', [$compliment, 0]))->assertOk();
    $this->get(route('sales-report-evidence.void', [$void, 'original']))->assertOk();
    $this->get(route('sales-report-evidence.compliment', [$compliment, 3]))->assertNotFound();

    $outsider = User::factory()->create(['branch_id' => Branch::factory()->create()->id, 'is_active' => true]);
    $outsider->givePermissionTo('view sales reports');
    $this->actingAs($outsider)->get(route('sales-report-evidence.void', [$void, 'original']))->assertForbidden();

    $finance = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $finance->givePermissionTo('view sales reports');
    $this->actingAs($finance)->get(route('sales-report-evidence.compliment', [$compliment, 0]))->assertOk();
});

it('deletes Void receipts with the Void or with its Sales Report exactly once', function () {
    $report = SalesReport::factory()->create(['branch_id' => $this->branch->id]);
    Storage::disk('b2')->put('sales-report-voids/a.jpg', 'x');
    Storage::disk('b2')->put('sales-report-voids/b.jpg', 'x');
    Storage::disk('b2')->put('sales-report-voids/c.jpg', 'x');
    Storage::disk('b2')->put('sales-report-voids/d.jpg', 'x');
    $single = SalesReportVoid::factory()->create(['sales_report_id' => $report->id, 'original_receipt_path' => 'sales-report-voids/a.jpg', 'voided_receipt_path' => 'sales-report-voids/b.jpg']);
    SalesReportVoid::factory()->create(['sales_report_id' => $report->id, 'original_receipt_path' => 'sales-report-voids/c.jpg', 'voided_receipt_path' => 'sales-report-voids/d.jpg']);

    $single->delete();
    Storage::disk('b2')->assertMissing(['sales-report-voids/a.jpg', 'sales-report-voids/b.jpg']);
    Storage::disk('b2')->assertExists(['sales-report-voids/c.jpg', 'sales-report-voids/d.jpg']);

    $report->delete();
    Storage::disk('b2')->assertMissing(['sales-report-voids/c.jpg', 'sales-report-voids/d.jpg']);
    expect(SalesReportVoid::query()->count())->toBe(0);
});
