<?php

use App\Enums\ItRequestStatus;
use App\Filament\Casual\Pages\ErpRequestPage;
use App\Filament\Helpdesk\Resources\ErpModules\Pages\ListErpModules;
use App\Filament\Helpdesk\Resources\ErpRepairRequests\Pages\ListErpRepairRequests;
use App\Filament\Helpdesk\Resources\ErpRepairRequests\Pages\ViewErpRepairRequest;
use App\Filament\Helpdesk\Resources\ItRequestTypes\Pages\EditItRequestType;
use App\Filament\Helpdesk\Resources\ItRequestTypes\Pages\ListItRequestTypes;
use App\Models\Branch;
use App\Models\ErpModule;
use App\Models\ErpRepairRequest;
use App\Models\ItRequestType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->branch = Branch::factory()->create();
    $this->module = ErpModule::create(['name' => 'Sales', 'sort_order' => 1, 'is_active' => true]);
    $this->type = ItRequestType::where('name', 'Ticketing')->firstOrFail();
    $this->user = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
});

it('creates an IT ticket from the user app with a unique ticket number', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->user);

    Livewire::test(ErpRequestPage::class)
        ->set('requestTypeId', (string) $this->type->id)
        ->set('erpModuleId', (string) $this->module->id)
        ->set('keterangan', 'Sales report tidak dapat dibuka.')
        ->call('submit')
        ->assertHasNoErrors();

    $request = ErpRepairRequest::firstOrFail();

    expect($request->ticket_number)->toMatch('/^IT-\d{6}$/')
        ->and($request->status)->toBe(ItRequestStatus::Submitted)
        ->and($request->request_type_id)->toBe($this->type->id)
        ->and($request->activities()->where('action', 'submitted')->exists())->toBeTrue();
});

it('derives the new ticket\'s priority from its Request Type\'s configured priority', function () {
    $this->type->update(['priority' => 'critical']);
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->user);

    Livewire::test(ErpRequestPage::class)
        ->set('requestTypeId', (string) $this->type->id)
        ->set('erpModuleId', (string) $this->module->id)
        ->set('keterangan', 'Sales report tidak dapat dibuka.')
        ->call('submit')
        ->assertHasNoErrors();

    expect(ErpRepairRequest::firstOrFail()->priority)->toBe('critical');
});

it('handles IT follow-up directly from the desktop ticket detail page', function () {
    $request = ErpRepairRequest::factory()->create([
        'requester_id' => $this->user->id,
        'branch_id' => $this->branch->id,
        'erp_module_id' => $this->module->id,
        'request_type_id' => $this->type->id,
        'status' => ItRequestStatus::Submitted,
        'priority' => 'high',
    ]);
    $itStaff = User::factory()->create(['is_active' => true]);
    $itStaff->assignRole('IT_STAFF');
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->actingAs($itStaff);

    Livewire::test(ViewErpRepairRequest::class, ['record' => $request->getRouteKey()])
        ->assertSee($request->ticket_number)
        ->call('transitionTo', ItRequestStatus::Review->value)
        ->assertHasNoErrors()
        ->call('transitionTo', ItRequestStatus::Approved->value)
        ->assertHasNoErrors()
        ->call('transitionTo', ItRequestStatus::Progress->value)
        ->assertHasNoErrors();

    // Priority is derived from the Request Type at creation and is no longer
    // editable from the follow-up flow — it must survive status transitions.
    expect($request->refresh()->status)->toBe(ItRequestStatus::Progress)
        ->and($request->priority)->toBe('high')
        ->and($request->activities()->where('action', 'status_changed')->exists())->toBeTrue();
});

it('only shows the notes input when the ticket can be rejected or completed', function () {
    $request = ErpRepairRequest::factory()->create([
        'requester_id' => $this->user->id,
        'branch_id' => $this->branch->id,
        'erp_module_id' => $this->module->id,
        'request_type_id' => $this->type->id,
        'status' => ItRequestStatus::Submitted,
    ]);
    $itStaff = User::factory()->create(['is_active' => true]);
    $itStaff->assignRole('IT_STAFF');
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->actingAs($itStaff);

    Livewire::test(ViewErpRepairRequest::class, ['record' => $request->getRouteKey()])
        ->assertDontSee('Save Notes')
        ->assertDontSee('Catatan (wajib diisi jika reject)...')
        ->call('transitionTo', ItRequestStatus::Review->value)
        ->assertSee('Catatan (wajib diisi jika reject)...')
        ->call('transitionTo', ItRequestStatus::Approved->value)
        ->assertDontSee('Catatan (wajib diisi jika reject)...')
        ->assertDontSee('Catatan penyelesaian...')
        ->call('transitionTo', ItRequestStatus::Progress->value)
        ->assertSee('Catatan penyelesaian...');
});

it('requires IT notes when rejecting a ticket at the review step', function () {
    $request = ErpRepairRequest::factory()->create([
        'requester_id' => $this->user->id,
        'branch_id' => $this->branch->id,
        'erp_module_id' => $this->module->id,
        'request_type_id' => $this->type->id,
        'status' => ItRequestStatus::Review,
    ]);
    $itStaff = User::factory()->create(['is_active' => true]);
    $itStaff->assignRole('IT_STAFF');
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->actingAs($itStaff);

    Livewire::test(ViewErpRepairRequest::class, ['record' => $request->getRouteKey()])
        ->set('itNotes', '')
        ->call('transitionTo', ItRequestStatus::Rejected->value)
        ->assertHasErrors(['itNotes']);

    expect($request->refresh()->status)->toBe(ItRequestStatus::Review);
});

it('walks an ERP ticket through the full sequential flow to completion', function () {
    $request = ErpRepairRequest::factory()->create([
        'requester_id' => $this->user->id,
        'branch_id' => $this->branch->id,
        'erp_module_id' => $this->module->id,
        'request_type_id' => $this->type->id,
        'status' => ItRequestStatus::Submitted,
    ]);
    $itStaff = User::factory()->create(['is_active' => true]);
    $itStaff->assignRole('IT_STAFF');
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->actingAs($itStaff);

    $page = Livewire::test(ViewErpRepairRequest::class, ['record' => $request->getRouteKey()]);

    foreach ([ItRequestStatus::Review, ItRequestStatus::Approved, ItRequestStatus::Progress, ItRequestStatus::Completed] as $nextStatus) {
        $page->call('transitionTo', $nextStatus->value)->assertHasNoErrors();
        expect($request->refresh()->status)->toBe($nextStatus);
    }

    expect($request->resolved_at)->not->toBeNull()
        ->and($request->activities()->where('action', 'status_changed')->count())->toBe(4);
});

it('renders the ERP repair request list with Purchasing-style columns, filters, and actions', function () {
    $request = ErpRepairRequest::factory()->create([
        'requester_id' => $this->user->id,
        'branch_id' => $this->branch->id,
        'erp_module_id' => $this->module->id,
        'request_type_id' => $this->type->id,
        'status' => ItRequestStatus::Submitted,
    ]);
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->actingAs($admin);

    $page = Livewire::test(ListErpRepairRequests::class)
        ->assertSuccessful()
        ->assertSee($request->ticket_number)
        ->assertSee('TIKET')
        ->assertSee('PEMOHON')
        ->assertSee('CABANG')
        ->assertSee('KETERANGAN')
        // Proves the Purchasing-style per-column header filter row (custom
        // TablesRenderHook::HEADER_CELL override) is actually rendering,
        // not Filament's default boxed "above content" filter panel.
        ->assertSee('Cari tiket...')
        ->assertSee('Cari pemohon...')
        ->assertSee('Cari keterangan...')
        ->assertSee('Pilih rentang tanggal')
        ->assertSee('- Semua Cabang -');

    // Matches Purchasing's row actions exactly: View + Delete, no Edit button.
    $page->assertTableActionVisible('view', $request)
        ->assertTableActionVisible('delete', $request)
        ->assertTableActionDoesNotExist('edit');

    $page->set('tableFilters.ticket_number.value', $request->ticket_number)
        ->assertCanSeeTableRecords([$request]);

    $page->set('tableFilters.ticket_number.value', '')
        ->set('tableFilters.requester_name.value', $this->user->name)
        ->assertCanSeeTableRecords([$request]);

    $page->set('tableFilters.requester_name.value', 'Nama Yang Tidak Ada Sama Sekali')
        ->assertCanNotSeeTableRecords([$request]);
});

it('keeps the Information Technology sidebar group open when viewing IT Request Types', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->actingAs($admin);

    $this->get(route('filament.helpdesk.resources.it-request-types.index'))
        ->assertOk()
        ->assertSee('openGroups: ["it"]', false);
});

it('styles the ERP Module and IT Request Type indexes like the ERP repair request index', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->actingAs($admin);

    Livewire::test(ListErpModules::class)
        ->assertSuccessful()
        ->assertSee('NAMA MODUL')
        ->assertSee('JUMLAH REQUEST')
        ->assertSee('AKTIF');

    Livewire::test(ListItRequestTypes::class)
        ->assertSuccessful()
        ->assertSee('NAME')
        ->assertSee('PRIORITY')
        ->assertSee('REQUESTS')
        ->assertSee('ACTIVE');
});

it('lets an admin configure a Request Type\'s default priority via the CMS form', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->actingAs($admin);

    Livewire::test(EditItRequestType::class, ['record' => $this->type->getRouteKey()])
        ->fillForm(['priority' => 'critical'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->type->refresh()->priority)->toBe('critical');
});

it('prevents status transitions from skipping the workflow sequence', function () {
    $request = ErpRepairRequest::factory()->create([
        'requester_id' => $this->user->id,
        'branch_id' => $this->branch->id,
        'erp_module_id' => $this->module->id,
        'request_type_id' => $this->type->id,
        'status' => ItRequestStatus::Submitted,
    ]);
    $itStaff = User::factory()->create(['is_active' => true]);
    $itStaff->assignRole('IT_STAFF');
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->actingAs($itStaff);

    Livewire::test(ViewErpRepairRequest::class, ['record' => $request->getRouteKey()])
        ->set('status', ItRequestStatus::Progress->value)
        ->call('saveFollowUp')
        ->assertHasErrors(['status']);

    expect($request->refresh()->status)->toBe(ItRequestStatus::Submitted);
});
