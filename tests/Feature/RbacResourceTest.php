<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function superAdmin(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('super_admin');

    return $user;
}

function regularUser(): User
{
    return User::factory()->create(['is_active' => true]);
}

// ─── Permissions auto-sync ────────────────────────────────────────────────────

it('permissions config is loaded and permissions exist in the database', function () {
    $config = config('permissions', []);
    expect($config)->not->toBeEmpty();

    $allPermissions = array_merge(...array_values(array_map(
        fn (array $resources) => array_merge(...array_values($resources)),
        $config
    )));

    foreach ($allPermissions as $permission) {
        expect(Permission::where('name', $permission)->exists())->toBeTrue(
            "Permission '{$permission}' missing from database"
        );
    }
});

it('super_admin role has all permissions', function () {
    $superAdminRole = Role::findByName('super_admin', 'web');
    $allPermissions = array_merge(...array_values(array_map(
        fn (array $resources) => array_merge(...array_values($resources)),
        config('permissions', [])
    )));

    foreach ($allPermissions as $permission) {
        expect($superAdminRole->hasPermissionTo($permission))->toBeTrue(
            "super_admin missing permission: {$permission}"
        );
    }
});

// ─── UserResource access ──────────────────────────────────────────────────────

it('super_admin can access user management', function () {
    actingAs(superAdmin())
        ->get('/admin/users')
        ->assertOk();
});

it('regular user cannot access user management', function () {
    actingAs(regularUser())
        ->get('/admin/users')
        ->assertForbidden();
});

it('super_admin can access user create page', function () {
    actingAs(superAdmin())
        ->get('/admin/users/create')
        ->assertOk();
});

// ─── RoleResource access ──────────────────────────────────────────────────────

it('super_admin can access role management', function () {
    actingAs(superAdmin())
        ->get('/admin/roles')
        ->assertOk();
});

it('regular user cannot access role management', function () {
    actingAs(regularUser())
        ->get('/admin/roles')
        ->assertForbidden();
});

it('super_admin can access role create page', function () {
    actingAs(superAdmin())
        ->get('/admin/roles/create')
        ->assertOk();
});

it('roles list page shows existing roles', function () {
    actingAs(superAdmin())
        ->get('/admin/roles')
        ->assertSee('super_admin');
});
