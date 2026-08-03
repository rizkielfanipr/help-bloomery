<?php

use App\Enums\ItRequestStatus;
use App\Filament\Casual\Pages\ErpRequestPage;
use App\Filament\Helpdesk\Resources\ErpRepairRequests\Pages\EditErpRepairRequest;
use App\Filament\Helpdesk\Resources\ErpRepairRequests\Pages\ViewErpRepairRequest;
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

it('records triage and escalation in the ticket timeline', function () {
    $request = ErpRepairRequest::factory()->create([
        'requester_id' => $this->user->id,
        'branch_id' => $this->branch->id,
        'erp_module_id' => $this->module->id,
        'request_type_id' => $this->type->id,
        'status' => ItRequestStatus::Progress,
    ]);
    $itStaff = User::factory()->create(['is_active' => true]);
    $itStaff->assignRole('IT_STAFF');
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->actingAs($itStaff);

    Livewire::test(EditErpRepairRequest::class, ['record' => $request->getRouteKey()])
        ->fillForm([
            'status' => ItRequestStatus::Escalated->value,
            'assignee_id' => $itStaff->id,
            'work_classification' => 'major_project',
            'priority' => 'high',
            'escalation_target' => 'developer',
            'escalation_reason' => 'Requires a source-code change.',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($request->refresh()->status)->toBe(ItRequestStatus::Escalated)
        ->and($request->escalated_at)->not->toBeNull()
        ->and($request->activities()->where('action', 'status_changed')->exists())->toBeTrue();
});

it('requires a resolution note before completing a ticket', function () {
    $request = ErpRepairRequest::factory()->create([
        'requester_id' => $this->user->id,
        'branch_id' => $this->branch->id,
        'erp_module_id' => $this->module->id,
        'request_type_id' => $this->type->id,
        'status' => ItRequestStatus::Progress,
        'work_classification' => 'standard',
    ]);
    $itStaff = User::factory()->create(['is_active' => true]);
    $itStaff->assignRole('IT_STAFF');
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->actingAs($itStaff);

    Livewire::test(EditErpRepairRequest::class, ['record' => $request->getRouteKey()])
        ->fillForm([
            'status' => ItRequestStatus::Completed->value,
            'assignee_id' => $itStaff->id,
            'work_classification' => 'standard',
            'priority' => 'medium',
            'resolution_note' => '',
        ])
        ->call('save')
        ->assertHasFormErrors(['resolution_note']);

    expect($request->refresh()->status)->toBe(ItRequestStatus::Progress);
});

it('handles IT follow-up directly from the desktop ticket detail page', function () {
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
        ->assertSee($request->ticket_number)
        ->assertSee('IT Follow-up')
        ->set('assigneeId', (string) $itStaff->id)
        ->set('classification', 'standard')
        ->set('priority', 'high')
        ->set('itNotes', 'Issue reproduced and under investigation.')
        ->call('transitionTo', ItRequestStatus::Review->value)
        ->assertHasNoErrors()
        ->call('transitionTo', ItRequestStatus::Progress->value)
        ->assertHasNoErrors();

    expect($request->refresh()->status)->toBe(ItRequestStatus::Progress)
        ->and($request->assignee_id)->toBe($itStaff->id)
        ->and($request->work_classification)->toBe('standard')
        ->and($request->activities()->where('action', 'status_changed')->exists())->toBeTrue();
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
        ->set('assigneeId', (string) $itStaff->id)
        ->set('classification', 'standard')
        ->call('saveFollowUp')
        ->assertHasErrors(['status']);

    expect($request->refresh()->status)->toBe(ItRequestStatus::Submitted);
});
