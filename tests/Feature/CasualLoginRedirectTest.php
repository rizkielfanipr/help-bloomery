<?php

use App\Filament\Casual\Pages\Auth\Login;
use App\Filament\Casual\Pages\LauncherPage;
use App\Filament\Casual\Pages\TechnicianHistoryPage;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

it('always redirects a casual login to the launcher instead of an old module URL', function () {
    Permission::findOrCreate('access employee app technician');
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo('access employee app technician');

    Filament::setCurrentPanel(Filament::getPanel('casual'));
    session()->put('url.intended', TechnicianHistoryPage::getUrl(panel: 'casual'));

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->username, 'password' => 'password'])
        ->call('authenticate')
        ->assertRedirect(LauncherPage::getUrl(panel: 'casual'));

    expect(session()->has('url.intended'))->toBeFalse();
});
