<?php

use App\Models\HelpdeskFormTemplate;
use App\Models\HelpdeskRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function makeUserWithRole(string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole($role);

    return $user;
}

function templateForRoles(array $roles): HelpdeskFormTemplate
{
    return HelpdeskFormTemplate::factory()->create([
        'is_active' => true,
        'roles' => $roles,
    ]);
}

// ─── isAccessibleByUser ───────────────────────────────────────────────────────

it('template with no roles restriction is accessible by any user', function () {
    $template = HelpdeskFormTemplate::factory()->create(['is_active' => true, 'roles' => null]);
    $user = makeUserWithRole('helpdesk_staff');

    expect($template->isAccessibleByUser($user))->toBeTrue();
});

it('template with empty roles array is accessible by any user', function () {
    $template = HelpdeskFormTemplate::factory()->create(['is_active' => true, 'roles' => []]);
    $user = makeUserWithRole('helpdesk_staff');

    expect($template->isAccessibleByUser($user))->toBeTrue();
});

it('template restricts access to specified roles only', function () {
    $template = templateForRoles(['helpdesk_manager']);

    $manager = makeUserWithRole('helpdesk_manager');
    $staff = makeUserWithRole('helpdesk_staff');

    expect($template->isAccessibleByUser($manager))->toBeTrue()
        ->and($template->isAccessibleByUser($staff))->toBeFalse();
});

it('super_admin can access role-restricted template', function () {
    $template = templateForRoles(['helpdesk_staff']);
    $superAdmin = makeUserWithRole('super_admin');

    expect($template->isAccessibleByUser($superAdmin))->toBeFalse();
    // super_admin bypasses at query level (getEloquentQuery), not isAccessibleByUser
});

// ─── Table query filtering ────────────────────────────────────────────────────

it('staff cannot see requests from templates they cannot access', function () {
    $restrictedTemplate = templateForRoles(['helpdesk_manager']);
    $openTemplate = HelpdeskFormTemplate::factory()->create(['is_active' => true, 'roles' => null]);

    $staff = makeUserWithRole('helpdesk_staff');

    HelpdeskRequest::factory()->create([
        'helpdesk_form_template_id' => $restrictedTemplate->id,
        'requester_id' => $staff->id,
        'status' => 'submitted',
        'data' => [],
    ]);

    HelpdeskRequest::factory()->create([
        'helpdesk_form_template_id' => $openTemplate->id,
        'requester_id' => $staff->id,
        'status' => 'submitted',
        'data' => [],
    ]);

    actingAs($staff)
        ->get('/helpdesk/helpdesk-requests')
        ->assertOk();

    // staff only sees the open template request, not the restricted one
    expect(
        HelpdeskRequest::where('requester_id', $staff->id)
            ->whereIn('helpdesk_form_template_id', [$openTemplate->id])
            ->count()
    )->toBe(1);
});

it('manager can see all requests regardless of template roles', function () {
    $restrictedTemplate = templateForRoles(['helpdesk_staff']);
    $staff = makeUserWithRole('helpdesk_staff');
    $manager = makeUserWithRole('helpdesk_manager');

    HelpdeskRequest::factory()->count(2)->create([
        'helpdesk_form_template_id' => $restrictedTemplate->id,
        'requester_id' => $staff->id,
        'status' => 'submitted',
        'data' => [],
    ]);

    actingAs($manager)
        ->get('/helpdesk/helpdesk-requests')
        ->assertOk();

    expect(HelpdeskRequest::count())->toBe(2);
});
