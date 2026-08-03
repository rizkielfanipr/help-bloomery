<?php

use App\Models\User;
use App\Services\PermissionRegistry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Panel;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function superAdmin(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('SUPERADMIN');

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

it('automatically discovers permissions declared by Filament resources', function () {
    $groups = app(PermissionRegistry::class)->groups();

    expect(config('permissions.Driver.Jenis BBM'))->toBeNull()
        ->and($groups['Driver']['Jenis BBM'])->toBe([
            'view fuel types',
            'create fuel types',
            'edit fuel types',
            'delete fuel types',
        ])
        ->and(Permission::where('name', 'view fuel types')->exists())->toBeTrue();
});

it('does not duplicate auto-discovered resources already registered in a module', function () {
    $groups = app(PermissionRegistry::class)->groups();

    expect($groups['Human Resources']['Absensi Casual'])->toContain('view clock records')
        ->and($groups['Casual Staff']['Monitoring Absensi Casual'] ?? null)->toBeNull();
});

it('keeps driver resources in the Driver permission module', function () {
    $groups = app(PermissionRegistry::class)->groups();

    expect($groups)->toHaveKey('Driver')
        ->and($groups)->not->toHaveKey('Manajemen Driver')
        ->and($groups['Driver'])->toHaveKeys(['Perjalanan', 'Rute Perjalanan', 'Kendaraan', 'Jenis BBM']);
});

it('bundles sales region access into Project and removes payment grouping permissions', function () {
    $groups = app(PermissionRegistry::class)->groups();

    expect($groups['Master'])->not->toHaveKeys(['Region Penjualan', 'Grup Metode Pembayaran'])
        ->and($groups['Research & Development']['Project'])->toContain('view rnd projects')
        ->and(Permission::where('name', 'view sales regions')->exists())->toBeFalse()
        ->and(Permission::where('name', 'view payment methods')->exists())->toBeFalse()
        ->and(Permission::where('name', 'view payment method groups')->exists())->toBeFalse();
});

it('SUPERADMIN role has all permissions', function () {
    $superAdminRole = Role::findByName('SUPERADMIN', 'web');
    $allPermissions = array_merge(...array_values(array_map(
        fn (array $resources) => array_merge(...array_values($resources)),
        config('permissions', [])
    )));

    foreach ($allPermissions as $permission) {
        expect($superAdminRole->hasPermissionTo($permission))->toBeTrue(
            "SUPERADMIN missing permission: {$permission}"
        );
    }
});

it('allows panel access from permission without hardcoded role names', function () {
    $role = Role::create(['name' => 'PURCHASING_STAFF', 'guard_name' => 'web']);
    $role->givePermissionTo(['access backoffice', 'view purchase requests']);

    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole($role);

    expect($user->canAccessPanel(Panel::make()->id('helpdesk')))->toBeTrue()
        ->and($user->canAccessPanel(Panel::make()->id('admin')))->toBeFalse();

    actingAs($user)
        ->get('/')
        ->assertOk();
});

it('requires both application access and feature permissions independently', function () {
    $featureOnly = User::factory()->create(['is_active' => true]);
    $featureOnly->givePermissionTo('view purchase requests');

    $appOnly = User::factory()->create(['is_active' => true]);
    $appOnly->givePermissionTo('access backoffice');

    expect($featureOnly->canAccessPanel(Panel::make()->id('helpdesk')))->toBeFalse()
        ->and($appOnly->canAccessPanel(Panel::make()->id('helpdesk')))->toBeTrue()
        ->and($appOnly->can('view purchase requests'))->toBeFalse();
});

it('allows a user without email to authenticate using username', function () {
    $user = User::factory()->create([
        'username' => 'BLONOEMAIL',
        'email' => null,
        'password' => 'password',
        'is_active' => true,
    ]);

    expect($user->email)->toBeNull()
        ->and(Auth::attempt(['username' => 'BLONOEMAIL', 'password' => 'password']))->toBeTrue();
});

it('separates employee app access from backoffice access', function () {
    $casual = User::factory()->create(['is_active' => true]);
    $casual->assignRole('CASUAL_STAFF');

    $hr = User::factory()->create(['is_active' => true]);
    $hr->assignRole('HRD_STAFF');

    expect($casual->canAccessPanel(Panel::make()->id('casual')))->toBeTrue()
        ->and($casual->canAccessPanel(Panel::make()->id('helpdesk')))->toBeFalse()
        ->and($hr->canAccessPanel(Panel::make()->id('casual')))->toBeTrue()
        ->and($hr->canAccessPanel(Panel::make()->id('helpdesk')))->toBeTrue();
});

it('never locks SUPERADMIN out when its stored permissions are stale', function () {
    $user = superAdmin();
    $user->roles->first()->syncPermissions([]);

    expect($user->fresh()->canAccessPanel(Panel::make()->id('helpdesk')))->toBeTrue()
        ->and($user->fresh()->canAccessPanel(Panel::make()->id('admin')))->toBeTrue();

    actingAs($user->fresh())
        ->get('/')
        ->assertOk();
});

it('keeps roles created dynamically when permissions are synchronized', function () {
    Role::create(['name' => 'PURCHASING_STAFF', 'guard_name' => 'web']);

    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::where('name', 'PURCHASING_STAFF')->exists())->toBeTrue();
});

it('removes obsolete panel and tile permissions', function () {
    expect(Permission::where('name', 'access helpdesk panel')->exists())->toBeFalse()
        ->and(Permission::where('name', 'view tile purchasing')->exists())->toBeFalse()
        ->and(Permission::where('name', 'view all branch data')->exists())->toBeFalse();
});

it('allows the R&D module based on permissions without checking the role name', function () {
    $rndStaff = User::factory()->create(['is_active' => true]);
    $rndStaff->assignRole('RND_STAFF');

    actingAs($rndStaff)
        ->get('/rnd-projects')
        ->assertOk();

    actingAs($rndStaff)
        ->get('/product-price-index')
        ->assertOk();

    actingAs($rndStaff)
        ->get('/bill-of-material/create')
        ->assertRedirect();
});

// ─── UserResource access ──────────────────────────────────────────────────────

it('SUPERADMIN can access user management', function () {
    actingAs(superAdmin())
        ->get('/admin/users')
        ->assertOk();
});

it('regular user cannot access user management', function () {
    actingAs(regularUser())
        ->get('/admin/users')
        ->assertForbidden();
});

it('SUPERADMIN can access user create page', function () {
    actingAs(superAdmin())
        ->get('/admin/users/create')
        ->assertOk();
});

// ─── RoleResource access ──────────────────────────────────────────────────────

it('SUPERADMIN can access role management', function () {
    actingAs(superAdmin())
        ->get('/admin/roles')
        ->assertOk();
});

it('regular user cannot access role management', function () {
    actingAs(regularUser())
        ->get('/admin/roles')
        ->assertForbidden();
});

it('SUPERADMIN can access role create page', function () {
    actingAs(superAdmin())
        ->get('/admin/roles/create')
        ->assertOk();
});

it('roles list page shows existing roles', function () {
    actingAs(superAdmin())
        ->get('/admin/roles')
        ->assertSee('SUPERADMIN');
});

it('shows application access permissions in the role matrix', function () {
    $response = actingAs(superAdmin())
        ->get('/admin/roles/create')
        ->assertOk()
        ->assertSee('Akses Employee App')
        ->assertSee('Absensi')
        ->assertSee('Purchasing')
        ->assertSee('Back Office')
        ->assertDontSee('Guard');

    Permission::where('name', 'like', 'access employee app%')
        ->pluck('id')
        ->each(fn (int $id) => $response->assertSee("toggle({$id})", escape: false));
});
