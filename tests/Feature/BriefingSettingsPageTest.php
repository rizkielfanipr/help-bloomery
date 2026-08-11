<?php

use App\Filament\Helpdesk\Pages\BriefingSettingsPage;
use App\Models\BriefingSettings;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('blocks access without the edit briefing settings permission', function () {
    $user = User::factory()->create(['is_active' => true]);
    actingAs($user);

    expect(BriefingSettingsPage::canAccess())->toBeFalse();
});

it('allows access with the edit briefing settings permission', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo('edit briefing settings');
    actingAs($user);

    expect(BriefingSettingsPage::canAccess())->toBeTrue();
});

it('defaults the form to the current 3-day setting', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo('edit briefing settings');
    actingAs($user);

    Livewire::test(BriefingSettingsPage::class)
        ->assertSet('data.auto_reject_after_days', 3);
});

it('saves an updated auto-reject window', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo('edit briefing settings');
    actingAs($user);

    Livewire::test(BriefingSettingsPage::class)
        ->fillForm(['auto_reject_after_days' => 5])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(BriefingSettings::instance()->auto_reject_after_days)->toBe(5);
});

it('renders the Pengaturan link under Daily Briefing in the custom helpdesk sidebar', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('HRD_STAFF');
    actingAs($user);

    $response = $this->get(route('filament.helpdesk.pages.briefing-settings-page'));

    $response->assertOk()
        ->assertSee('Daily Briefing')
        ->assertSee(route('filament.helpdesk.pages.briefing-settings-page'), false);
});
