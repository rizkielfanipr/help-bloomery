<?php

use App\Filament\Casual\Pages\Auth\Register;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('casual'));
});

test('casual registration screen can be rendered', function () {
    $this->get(route('filament.casual.auth.register'))->assertOk();
});

test('new casual users can register with the active form contract', function () {
    Livewire::test(Register::class)
        ->fillForm([
            'name' => 'Test Casual',
            'username' => 'test.casual',
            'phone' => '081234567890',
            'bank_name' => 'BCA',
            'bank_account_number' => '1234567890',
            'password' => 'password',
            'passwordConfirmation' => 'password',
        ])
        ->call('register')
        ->assertHasNoFormErrors();

    $user = User::query()->where('username', 'test.casual')->firstOrFail();

    $this->assertAuthenticatedAs($user);
    expect($user->email)->toBe('081234567890@casual.app')
        ->and($user->hasRole('CASUAL_STAFF'))->toBeTrue();
});
