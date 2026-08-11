<?php

use App\Filament\Helpdesk\Pages\BriefingPeriodWeightsPage;
use App\Models\Branch;
use App\Models\BriefingPeriodWeight;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('blocks access without the edit briefing period weights permission', function () {
    $user = User::factory()->create(['is_active' => true]);
    actingAs($user);

    expect(BriefingPeriodWeightsPage::canAccess())->toBeFalse();
});

it('allows access with the edit briefing period weights permission', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo('edit briefing period weights');
    actingAs($user);

    expect(BriefingPeriodWeightsPage::canAccess())->toBeTrue();
});

it('defaults the first row to the global 40/30/30 split and lists active branches', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo('edit briefing period weights');
    actingAs($user);

    Branch::factory()->create(['is_active' => true, 'name' => 'Cabang Uji']);

    Livewire::test(BriefingPeriodWeightsPage::class)
        ->assertSet('rows.0.branch_id', null)
        ->assertSet('rows.0.daily_weight', 40.0)
        ->assertSet('rows.0.weekly_weight', 30.0)
        ->assertSet('rows.0.monthly_weight', 30.0)
        ->assertSee('Cabang Uji');
});

it('saves an updated global default', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo('edit briefing period weights');
    actingAs($user);

    Livewire::test(BriefingPeriodWeightsPage::class)
        ->set('rows.0.daily_weight', 50)
        ->set('rows.0.weekly_weight', 25)
        ->set('rows.0.monthly_weight', 25)
        ->call('save')
        ->assertHasNoErrors();

    expect(BriefingPeriodWeight::forBranch(null))->toBe(['daily' => 50.0, 'weekly' => 25.0, 'monthly' => 25.0]);
});

it('rejects a row whose weights do not sum to 100', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo('edit briefing period weights');
    actingAs($user);

    Livewire::test(BriefingPeriodWeightsPage::class)
        ->set('rows.0.daily_weight', 50)
        ->set('rows.0.weekly_weight', 30)
        ->set('rows.0.monthly_weight', 30)
        ->call('save')
        ->assertHasErrors(['rows.0.daily_weight']);

    expect(BriefingPeriodWeight::forBranch(null))->toBe(['daily' => 40.0, 'weekly' => 30.0, 'monthly' => 30.0]);
});

it('saves a branch-specific override without touching the global default', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo('edit briefing period weights');
    actingAs($user);

    $branch = Branch::factory()->create(['is_active' => true]);

    Livewire::test(BriefingPeriodWeightsPage::class)
        ->set('rows.1.daily_weight', 60)
        ->set('rows.1.weekly_weight', 20)
        ->set('rows.1.monthly_weight', 20)
        ->call('save')
        ->assertHasNoErrors();

    expect(BriefingPeriodWeight::forBranch($branch->id))->toBe(['daily' => 60.0, 'weekly' => 20.0, 'monthly' => 20.0])
        ->and(BriefingPeriodWeight::forBranch(null))->toBe(['daily' => 40.0, 'weekly' => 30.0, 'monthly' => 30.0]);
});

it('clears a branch override when the row is emptied back out, falling back to default', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo('edit briefing period weights');
    actingAs($user);

    $branch = Branch::factory()->create(['is_active' => true]);
    BriefingPeriodWeight::create(['branch_id' => $branch->id, 'daily_weight' => 60, 'weekly_weight' => 20, 'monthly_weight' => 20]);

    Livewire::test(BriefingPeriodWeightsPage::class)
        ->set('rows.1.daily_weight', null)
        ->set('rows.1.weekly_weight', null)
        ->set('rows.1.monthly_weight', null)
        ->call('save')
        ->assertHasNoErrors();

    expect(BriefingPeriodWeight::where('branch_id', $branch->id)->exists())->toBeFalse()
        ->and(BriefingPeriodWeight::forBranch($branch->id))->toBe(['daily' => 40.0, 'weekly' => 30.0, 'monthly' => 30.0]);
});

it('renders the Bobot Penilaian link under Daily Briefing in the custom helpdesk sidebar', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('HRD_STAFF');
    actingAs($user);

    $response = $this->get(route('filament.helpdesk.pages.briefing-period-weights-page'));

    $response->assertOk()
        ->assertSee('Daily Briefing')
        ->assertSee(route('filament.helpdesk.pages.briefing-period-weights-page'), false);
});
