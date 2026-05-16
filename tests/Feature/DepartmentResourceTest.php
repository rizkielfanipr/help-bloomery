<?php

use App\Models\Department;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function deptSuperAdmin(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('super_admin');

    return $user;
}

function deptRegularUser(): User
{
    return User::factory()->create(['is_active' => true]);
}

it('super_admin can access department list', function () {
    actingAs(deptSuperAdmin())
        ->get('/helpdesk/departments')
        ->assertOk();
});

it('regular user cannot access department list', function () {
    actingAs(deptRegularUser())
        ->get('/helpdesk/departments')
        ->assertForbidden();
});

it('super_admin can access department create page', function () {
    actingAs(deptSuperAdmin())
        ->get('/helpdesk/departments/create')
        ->assertOk();
});

it('department list shows existing departments', function () {
    Department::factory()->create(['name' => 'Information Technology', 'code' => 'IT']);

    actingAs(deptSuperAdmin())
        ->get('/helpdesk/departments')
        ->assertSee('Information Technology');
});

it('department edit page loads for existing record', function () {
    $dept = Department::factory()->create();

    actingAs(deptSuperAdmin())
        ->get("/helpdesk/departments/{$dept->id}/edit")
        ->assertOk();
});
