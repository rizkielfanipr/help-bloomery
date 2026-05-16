<?php

use App\Enums\FormFieldType;
use App\Enums\RequestStatus;
use App\Models\HelpdeskFormField;
use App\Models\HelpdeskFormTemplate;
use App\Models\HelpdeskRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function helpdeskStaff(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('helpdesk_staff');

    return $user;
}

function helpdeskManager(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('helpdesk_manager');

    return $user;
}

function makeTemplate(array $fields = []): HelpdeskFormTemplate
{
    $template = HelpdeskFormTemplate::factory()->create();

    foreach ($fields as $i => $field) {
        HelpdeskFormField::factory()->create([
            'helpdesk_form_template_id' => $template->id,
            'label' => $field['label'],
            'type' => $field['type'],
            'is_required' => $field['is_required'] ?? false,
            'sort_order' => $i,
        ]);
    }

    return $template;
}

test('helpdesk staff can view requests list page', function () {
    actingAs(helpdeskStaff())
        ->get('/helpdesk/helpdesk-requests')
        ->assertSuccessful();
});

test('helpdesk manager can view requests list page', function () {
    actingAs(helpdeskManager())
        ->get('/helpdesk/helpdesk-requests')
        ->assertSuccessful();
});

test('helpdesk staff can view create request page', function () {
    actingAs(helpdeskStaff())
        ->get('/helpdesk/helpdesk-requests/create')
        ->assertSuccessful();
});

test('helpdesk request is saved to database when created', function () {
    $template = makeTemplate([
        ['label' => 'Judul', 'type' => FormFieldType::Text->value, 'is_required' => true],
    ]);

    $user = helpdeskStaff();

    HelpdeskRequest::create([
        'helpdesk_form_template_id' => $template->id,
        'requester_id' => $user->id,
        'status' => RequestStatus::Submitted->value,
        'data' => ["field_{$template->fields->first()->id}" => 'Test Permintaan'],
    ]);

    $this->assertDatabaseHas('helpdesk_requests', [
        'helpdesk_form_template_id' => $template->id,
        'requester_id' => $user->id,
        'status' => RequestStatus::Submitted->value,
    ]);
});

test('helpdesk staff only sees their own requests', function () {
    $template = makeTemplate();

    $staff = helpdeskStaff();
    $otherStaff = helpdeskStaff();

    $ownRequest = HelpdeskRequest::factory()->create([
        'helpdesk_form_template_id' => $template->id,
        'requester_id' => $staff->id,
        'status' => RequestStatus::Submitted->value,
        'data' => [],
    ]);

    $otherRequest = HelpdeskRequest::factory()->create([
        'helpdesk_form_template_id' => $template->id,
        'requester_id' => $otherStaff->id,
        'status' => RequestStatus::Submitted->value,
        'data' => [],
    ]);

    actingAs($staff)
        ->get('/helpdesk/helpdesk-requests')
        ->assertSuccessful();

    expect(
        HelpdeskRequest::where('requester_id', $staff->id)->count()
    )->toBe(1);
});

test('helpdesk manager can see all requests', function () {
    $template = makeTemplate();

    HelpdeskRequest::factory()->count(3)->create([
        'helpdesk_form_template_id' => $template->id,
        'requester_id' => helpdeskStaff()->id,
        'data' => [],
    ]);

    actingAs(helpdeskManager())
        ->get('/helpdesk/helpdesk-requests')
        ->assertSuccessful();

    expect(HelpdeskRequest::count())->toBe(3);
});

test('helpdesk manager can view form templates page', function () {
    actingAs(helpdeskManager())
        ->get('/helpdesk/form-templates')
        ->assertSuccessful();
});

test('helpdesk staff cannot access form templates page', function () {
    actingAs(helpdeskStaff())
        ->get('/helpdesk/form-templates')
        ->assertForbidden();
});

test('status change is recorded in activity log via manual log', function () {
    $template = makeTemplate();
    $staff = helpdeskStaff();

    $request = HelpdeskRequest::create([
        'helpdesk_form_template_id' => $template->id,
        'requester_id' => $staff->id,
        'status' => RequestStatus::Submitted->value,
        'data' => [],
    ]);

    actingAs($staff);

    $oldStatus = $request->status;
    $request->update(['status' => 'in_progress']);

    activity()
        ->performedOn($request)
        ->causedBy($staff)
        ->withProperties(['from' => $oldStatus, 'to' => 'in_progress', 'label' => 'In Progress'])
        ->log('status_changed');

    $activity = Activity::where('subject_type', HelpdeskRequest::class)
        ->where('subject_id', $request->id)
        ->where('description', 'status_changed')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties['to'])->toBe('in_progress')
        ->and($activity->properties['from'])->toBe(RequestStatus::Submitted->value)
        ->and($activity->causer_id)->toBe($staff->id);
});

test('view page shows status history section', function () {
    $template = makeTemplate();
    $staff = helpdeskStaff();

    $request = HelpdeskRequest::create([
        'helpdesk_form_template_id' => $template->id,
        'requester_id' => $staff->id,
        'status' => RequestStatus::Submitted->value,
        'data' => [],
    ]);

    actingAs($staff);
    $request->update(['status' => 'in_progress']);

    actingAs($staff)
        ->get("/helpdesk/helpdesk-requests/{$request->id}")
        ->assertOk()
        ->assertSee('Riwayat Status');
});

test('form template can have multiple field types', function () {
    $template = makeTemplate([
        ['label' => 'Teks', 'type' => FormFieldType::Text->value],
        ['label' => 'Angka', 'type' => FormFieldType::Number->value],
        ['label' => 'Pilihan', 'type' => FormFieldType::Select->value],
        ['label' => 'Tanggal', 'type' => FormFieldType::Date->value],
        ['label' => 'File', 'type' => FormFieldType::File->value],
        ['label' => 'Toggle', 'type' => FormFieldType::Toggle->value],
        ['label' => 'Teks Panjang', 'type' => FormFieldType::Textarea->value],
    ]);

    expect($template->fields)->toHaveCount(7);
});
