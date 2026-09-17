<?php

use App\Enums\ServiceRequestStatus;
use App\Filament\Casual\Pages\TechnicianHistoryPage;
use App\Filament\Casual\Pages\TechnicianMaintenancePage;
use App\Filament\Casual\Pages\TechnicianRequestHistoryPage;
use App\Filament\Casual\Pages\TechnicianRequestPage;
use App\Filament\Casual\Resources\ServiceRequests\Pages\ListServiceRequests;
use App\Filament\Technician\Resources\ServiceRequests\Pages\ViewServiceRequest;
use App\Models\Asset;
use App\Models\Branch;
use App\Models\ServiceRequest;
use App\Models\TechnicianMaintenance;
use App\Models\User;
use App\Services\ServiceRequestWorkflow;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    Storage::fake('b2');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->branch = Branch::factory()->create();
    $this->reporter = User::factory()->create(['is_active' => true]);
    $this->reporter->syncBranchAccess([$this->branch->id], $this->branch->id);
    $this->technician = User::factory()->create(['is_active' => true]);
    $this->technician->assignRole('TECHNICIAN');
    $this->technician->syncBranchAccess([$this->branch->id], $this->branch->id);
    $this->asset = Asset::factory()->create(['branch_id' => $this->branch->id]);
    $this->request = ServiceRequest::factory()->create([
        'asset_id' => $this->asset->id,
        'branch_id' => $this->branch->id,
        'scheduled_by' => $this->reporter->id,
        'technician_id' => $this->technician->id,
        'status' => ServiceRequestStatus::Submitted,
        'scheduled_date' => null,
    ]);
});

it('submits a manual request without asset selection or a user schedule', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->reporter);
    Livewire::test(TechnicianRequestPage::class)
        ->assertDontSee('Tanggal Jadwal')
        ->assertDontSee('Atau tempel URL / kode QR')
        ->assertSee('Scan atau upload QR asset.')
        ->assertSee('QR yang terbaca akan membuka halaman asset untuk membuat laporan kendala.')
        ->assertSee('Upload Gambar QR')
        ->assertDontSee('Scan QR Asset')
        ->assertDontSee('Asset (Opsional)')
        ->assertSee('Manual khusus asset yang belum memiliki QR.')
        ->assertSee('agar laporan terhubung ke riwayat perbaikan asset.')
        ->set('requestorNotes', 'Mesin berhenti saat digunakan')
        ->call('submit')->assertHasNoErrors()->assertSet('submitted', true);
    $request = ServiceRequest::query()->latest('id')->first();
    expect($request->scheduled_date)->toBeNull()->and($request->asset_id)->toBeNull()->and($request->source)->toBe('manual');
});

it('allows the assigned technician to schedule and diagnose an unsafe asset when starting work', function () {
    Filament::setCurrentPanel(Filament::getPanel('technician'));
    $this->actingAs($this->technician);
    Livewire::test(ViewServiceRequest::class, ['record' => $this->request->id])
        ->assertSee('Atur Jadwal')
        ->assertDontSee('Pemeriksaan Asset')
        ->callAction('schedule', ['scheduled_date' => today()->toDateString(), 'priority' => 'urgent'])
        ->assertHasNoErrors()
        ->callAction('mulai_kerjakan', ['notes' => 'Motor rusak', 'photo' => ['before.jpg'], 'asset_condition' => 'unsafe'])
        ->assertHasNoErrors();
    expect($this->request->refresh()->status)->toBe(ServiceRequestStatus::InProgress)
        ->and($this->request->diagnosis)->toBe('Motor rusak')
        ->and($this->request->repairs()->count())->toBe(1)
        ->and($this->asset->refresh()->status)->toBe('Inactive');
});

it('routes vendor reports from the requester to technician verification and records a completed repair', function () {
    $workflow = app(ServiceRequestWorkflow::class);
    expect($this->asset->status)->toBe('Maintenance');
    $workflow->outsource($this->request, $this->technician, ['outsource_reason' => 'Penggantian motor oleh vendor']);
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->reporter);
    Livewire::test(TechnicianRequestHistoryPage::class)
        ->call('openOutsourceReport', $this->request->id)
        ->set('vendorName', 'Vendor Mesin')
        ->set('vendorDate', today()->toDateString())
        ->set('vendorNotes', 'Motor telah diganti dan diuji')
        ->set('vendorCost', '150000')
        ->set('vendorFiles', [UploadedFile::fake()->create('service-report.pdf', 20, 'application/pdf')])
        ->call('submitOutsourceReport')->assertHasNoErrors()->assertSet('outsourceRequestId', null);
    expect($this->request->refresh()->status)->toBe(ServiceRequestStatus::AwaitingVerification);
    Filament::setCurrentPanel(Filament::getPanel('technician'));
    $this->actingAs($this->technician);
    Livewire::test(ViewServiceRequest::class, ['record' => $this->request->id])
        ->assertSee('Vendor Mesin')
        ->callAction('verify_outsource', ['result' => 'approved', 'asset_condition' => 'safe', 'notes' => 'Hasil uji aman'])
        ->assertHasNoErrors();
    expect($this->request->refresh()->status)->toBe(ServiceRequestStatus::Warranty)
        ->and($this->asset->refresh()->status)->toBe('Active')
        ->and($this->asset->completedRepairs()->count())->toBe(1)
        ->and($this->request->repairs()->sole()->outsource_report['vendor'])->toBe('Vendor Mesin');
});

it('keeps an asset under maintenance while another request is open and protects inactive conditions', function () {
    $other = ServiceRequest::factory()->create(['asset_id' => $this->asset->id, 'branch_id' => $this->branch->id, 'status' => ServiceRequestStatus::Submitted]);
    $this->request->update(['status' => ServiceRequestStatus::Warranty, 'asset_condition' => 'safe']);
    app(ServiceRequestWorkflow::class)->updateAssetAfterRepair($this->request);
    expect($this->asset->fresh()->status)->toBe('Maintenance');
    $other->update(['asset_condition' => 'unsafe']);
    $this->asset->update(['is_active' => false]);
    app(ServiceRequestWorkflow::class)->updateAssetAfterRepair($this->request);
    expect($this->asset->fresh()->status)->toBe('Inactive');
});

it('rejects scheduling by the requester and report uploads from a different user', function () {
    $workflow = app(ServiceRequestWorkflow::class);
    expect(fn () => $workflow->schedule($this->request, $this->reporter, ['scheduled_date' => today(), 'priority' => 'normal']))->toThrow(HttpException::class);
    $this->request->update(['status' => ServiceRequestStatus::Outsource]);
    expect(fn () => $workflow->submitOutsourceReport($this->request, $this->technician, []))->toThrow(HttpException::class);
});

it('completes internal repairs once and reopens asset maintenance for a warranty claim', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->technician);
    $page = Livewire::test(App\Filament\Casual\Resources\ServiceRequests\Pages\ViewServiceRequest::class, ['record' => $this->request->id])
        ->assertSee('Perlu Outsource')
        ->assertSee('Informasi Asset')
        ->assertSee('Detail Kendala')
        ->assertSee('Jadwal Pengerjaan')
        ->assertSee($this->request->code)
        ->assertSee($this->asset->name)
        ->assertSee($this->asset->asset_number)
        ->callAction('mulai_kerjakan', ['notes' => 'Motor macet', 'photo' => ['before.jpg'], 'asset_condition' => 'safe'])
        ->assertHasNoErrors()
        ->assertSee('Riwayat Perbaikan')
        ->callAction('awaiting_parts')->assertHasNoErrors()
        ->callAction('resume')->assertHasNoErrors()
        ->callAction('selesai_kerjakan', ['notes' => 'Motor diganti dan diuji', 'photo' => ['after.jpg'], 'asset_condition' => 'safe'])
        ->assertHasNoErrors();
    expect($this->asset->completedRepairs()->count())->toBe(1)->and($this->asset->fresh()->status)->toBe('Active');
    $workflow = app(ServiceRequestWorkflow::class);
    expect(fn () => $workflow->complete($this->request, $this->technician, ['notes' => 'Duplikat', 'photo' => [], 'asset_condition' => 'safe']))->toThrow(ValidationException::class);
    $this->actingAs($this->reporter);
    Livewire::test(TechnicianRequestHistoryPage::class)
        ->call('startClaim', $this->request->id)->set('claimNotes', 'Motor kembali tidak dapat menyala')
        ->call('submitClaim')->assertHasNoErrors();
    expect($this->asset->fresh()->status)->toBe('Maintenance')->and($this->asset->completedRepairs()->count())->toBe(1);
});

it('returns rejected vendor reports to the requester without recording a completed repair', function () {
    $workflow = app(ServiceRequestWorkflow::class);
    $this->request->update(['status' => ServiceRequestStatus::AwaitingVerification, 'outsource_report' => ['vendor' => 'Vendor', 'files' => ['report.pdf']]]);
    $workflow->verifyOutsource($this->request, $this->technician, ['result' => 'revision', 'asset_condition' => 'unsafe', 'notes' => 'Lampirkan hasil uji']);
    expect($this->request->refresh()->status)->toBe(ServiceRequestStatus::Outsource)->and($this->asset->completedRepairs()->count())->toBe(0);
});

it('renders the technician work overview with asset branch schedule and actual request code', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->technician);
    Livewire::test(ListServiceRequests::class)
        ->assertSee('Logbook Teknisi')
        ->assertSee('Total Aktif')
        ->assertSee('Belum Dijadwal')
        ->assertSee($this->request->code)
        ->assertSee($this->asset->name)
        ->assertSee($this->asset->asset_number)
        ->assertSee($this->branch->name)
        ->assertSee('Menunggu Penjadwalan Teknisi');
    $this->request->update(['status' => ServiceRequestStatus::Completed]);
    Livewire::test(ListServiceRequests::class)
        ->assertSee('Tidak Ada Pekerjaan Aktif')
        ->assertDontSee($this->request->code);
});

it('renders the maintenance workspace and its saved checklist cards', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->technician);
    Livewire::test(TechnicianMaintenancePage::class)
        ->assertSee('Technician Workspace')
        ->assertSee('Ringkasan Maintenance')
        ->assertSee('Cabang Yang Dicek')
        ->assertSee('Mulai Pengecekan')
        ->assertSee('Belum Ada Maintenance');
    $maintenance = TechnicianMaintenance::query()->create([
        'branch_id' => $this->branch->id,
        'technician_id' => $this->technician->id,
        'checked_at' => today(),
        'maintenance_year' => today()->year,
        'maintenance_month' => today()->month,
        'status' => 'draft',
    ]);
    Livewire::test(TechnicianMaintenancePage::class)
        ->assertSee($maintenance->maintenance_number)
        ->assertSee($this->branch->name)
        ->assertSee('Checklist Belum Dikirim')
        ->assertDontSee('Belum Ada Maintenance');
});

it('shows the technician history overview with repair results and warranty date', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->technician);
    Livewire::test(TechnicianHistoryPage::class)->assertSee('Belum Ada Riwayat')->assertDontSee($this->request->code);
    $this->request->update(['status' => ServiceRequestStatus::Warranty, 'warranty_expires_at' => today()->addDays(30)]);
    $this->request->repairs()->create(['technician_id' => $this->technician->id, 'cycle' => 1, 'completed_at' => now(), 'after_notes' => 'Mesin telah diuji dan berfungsi normal']);
    Livewire::test(TechnicianHistoryPage::class)
        ->assertSee('Riwayat Pekerjaan')
        ->assertSee('Total Riwayat')
        ->assertSee($this->request->code)
        ->assertSee($this->asset->name)
        ->assertSee($this->branch->name)
        ->assertSee('Mesin telah diuji dan berfungsi normal')
        ->assertSee('Garansi Hingga')
        ->assertDontSee('Belum Ada Riwayat');
});
