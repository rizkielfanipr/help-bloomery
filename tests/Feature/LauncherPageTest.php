<?php

use App\Filament\Casual\Pages\LauncherPage;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('redirects guests to login', function () {
    get(route('filament.casual.pages.launcher-page'))
        ->assertRedirect();
});

it('shows Absensi Casual tile for casual_staff', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('casual_staff');

    actingAs($user);

    Livewire::test(LauncherPage::class)
        ->assertSee('Absensi Casual');
});

it('shows Logbook Driver tile for driver', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('driver');

    actingAs($user);

    Livewire::test(LauncherPage::class)
        ->assertSee('Logbook Driver');
});

it('shows Logbook Teknisi tile for technician', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('technician');

    actingAs($user);

    Livewire::test(LauncherPage::class)
        ->assertSee('Logbook Teknisi');
});

it('hides role-specific tiles from users without that role', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('casual_staff');

    actingAs($user);

    Livewire::test(LauncherPage::class)
        ->assertDontSee('Logbook Driver')
        ->assertDontSee('Logbook Teknisi');
});

it('shows coming soon tiles with Segera badge for all authenticated users', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('casual_staff');

    actingAs($user);

    Livewire::test(LauncherPage::class)
        ->assertSee('Daily Briefing')
        ->assertSee('Request Purchasing')
        ->assertSee('Request Desain')
        ->assertSee('Request ERP')
        ->assertSee('Segera');
});

it('shows all available tiles for super_admin', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('super_admin');

    actingAs($user);

    Livewire::test(LauncherPage::class)
        ->assertSee('Absensi Casual')
        ->assertSee('Logbook Driver')
        ->assertSee('Logbook Teknisi');
});
