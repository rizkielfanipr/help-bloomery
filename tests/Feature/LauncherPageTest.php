<?php

use App\Filament\Casual\Pages\LauncherPage;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('casual'));
});

it('redirects guests to login', function () {
    get(route('filament.casual.pages.launcher-page'))
        ->assertRedirect();
});

it('shows Absensi tile for CASUAL_STAFF', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('CASUAL_STAFF');

    actingAs($user);

    Livewire::test(LauncherPage::class)
        ->assertSee('Absensi');
});

it('shows Driver tile for DRIVER', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('DRIVER');

    actingAs($user);

    Livewire::test(LauncherPage::class)
        ->assertSee('Driver');
});

it('shows Teknisi tile for TECHNICIAN', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('TECHNICIAN');

    actingAs($user);

    Livewire::test(LauncherPage::class)
        ->assertSee('Teknisi');
});

it('hides role-specific tiles from users without that role', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('CASUAL_STAFF');

    actingAs($user);

    Livewire::test(LauncherPage::class)
        ->assertDontSee('Driver')
        ->assertDontSee('Teknisi');
});

it('only shows feature tiles granted by permissions', function () {
    $user = User::factory()->create(['is_active' => true]);
    $role = Role::create([
        'name' => 'PURCHASING_STAFF',
        'guard_name' => 'web',
    ]);
    $role->givePermissionTo('access employee app purchasing');
    $user->assignRole($role);

    actingAs($user);

    Livewire::test(LauncherPage::class)
        ->assertSee('Purchasing')
        ->assertDontSee('Daily Briefing')
        ->assertDontSee('Request Desain')
        ->assertDontSee('Request ERP');
});

it('blocks direct Employee App URLs for menus that are not granted', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('CASUAL_STAFF');

    actingAs($user)
        ->get(route('filament.casual.pages.purchase-request-page'))
        ->assertForbidden();
});

it('shows all available tiles for SUPERADMIN', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('SUPERADMIN');

    actingAs($user);

    Livewire::test(LauncherPage::class)
        ->assertSee('Absensi')
        ->assertSee('Driver')
        ->assertSee('Teknisi');
});
