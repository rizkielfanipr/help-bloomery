<?php

use App\Filament\Helpdesk\Pages\Auth\Login;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
});

test('helpdesk login screen can be rendered', function () {
    $this->get(route('filament.helpdesk.auth.login'))->assertOk();
});

test('authorized users can authenticate using username', function () {
    $user = User::factory()->create(['is_active' => true, 'username' => 'helpdesk.user']);
    $user->assignRole('SUPERADMIN');

    Livewire::test(Login::class)
        ->fillForm(['email' => 'helpdesk.user', 'password' => 'password'])
        ->call('authenticate');

    $this->assertAuthenticatedAs($user);
});

test('users cannot authenticate with an invalid password', function () {
    $user = User::factory()->create(['is_active' => true, 'username' => 'helpdesk.user']);
    $user->assignRole('SUPERADMIN');

    Livewire::test(Login::class)
        ->fillForm(['email' => 'helpdesk.user', 'password' => 'wrong-password'])
        ->call('authenticate');

    $this->assertGuest();
});

test('users can logout from the helpdesk panel', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('SUPERADMIN');

    $response = $this->actingAs($user)->post(route('filament.helpdesk.auth.logout'));

    $this->assertGuest();
    $response->assertRedirect(route('filament.helpdesk.auth.login'));
});
