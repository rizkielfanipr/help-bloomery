<?php

use App\Filament\Helpdesk\Pages\BriefingSettingsPage;
use App\Models\Branch;
use App\Models\BriefingBranchSetting;
use App\Models\BriefingPeriodWeight;
use App\Models\BriefingScore;
use App\Models\BriefingSettings;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * docs/products/daily-briefing/scoring-ui/prd.md §14/Phase 6/§21 (contract change: this page is now a
 * hand-built Blade form bound with plain `wire:model`, matching Sales Report Settings, instead of
 * a Filament Schema form — so these tests use plain Livewire `->set()`/`->assertSet()` rather
 * than the Filament-specific `->fillForm()`/`->assertHasNoFormErrors()` helpers).
 */
beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('blocks access without the edit briefing settings permission', function () {
    $user = User::factory()->create(['is_active' => true]);
    actingAs($user);

    expect(BriefingSettingsPage::canAccess())->toBeFalse();
});

it('allows access only when the user has both edit briefing settings and edit briefing period weights', function () {
    $settingsOnly = User::factory()->create(['is_active' => true]);
    $settingsOnly->givePermissionTo('edit briefing settings');
    actingAs($settingsOnly);
    expect(BriefingSettingsPage::canAccess())->toBeFalse();

    $weightsOnly = User::factory()->create(['is_active' => true]);
    $weightsOnly->givePermissionTo('edit briefing period weights');
    actingAs($weightsOnly);
    expect(BriefingSettingsPage::canAccess())->toBeFalse();

    $both = User::factory()->create(['is_active' => true]);
    $both->givePermissionTo(['edit briefing settings', 'edit briefing period weights']);
    actingAs($both);
    expect(BriefingSettingsPage::canAccess())->toBeTrue();
});

it('defaults the form to the current settings and default weights', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['edit briefing settings', 'edit briefing period weights']);
    actingAs($user);

    Livewire::test(BriefingSettingsPage::class)
        ->assertSet('data.auto_reject_after_days', 3)
        ->assertSet('data.auto_reject_reason', 'Tidak ada approval dalam :days hari setelah poin diselesaikan.')
        ->assertSet('data.passing_score', 80.0)
        ->assertSet('data.default_daily_weight', 40.0)
        ->assertSet('data.default_weekly_weight', 30.0)
        ->assertSet('data.default_monthly_weight', 30.0);
});

it('saves updated global settings', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['edit briefing settings', 'edit briefing period weights']);
    actingAs($user);

    Livewire::test(BriefingSettingsPage::class)
        ->set('data.auto_reject_after_days', 5)
        ->set('data.auto_reject_reason', 'Poin ditolak otomatis setelah :days hari tanpa approval.')
        ->set('data.passing_score', 85)
        ->set('data.scoring_started_at', '2026-01-01')
        ->call('save')
        ->assertHasNoErrors();

    $settings = BriefingSettings::instance();
    expect($settings->auto_reject_after_days)->toBe(5)
        ->and($settings->auto_reject_reason)->toBe('Poin ditolak otomatis setelah :days hari tanpa approval.')
        ->and($settings->passing_score)->toBe(85.0);
});

it('saves updated default weights when the total is exactly 100%', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['edit briefing settings', 'edit briefing period weights']);
    actingAs($user);

    Livewire::test(BriefingSettingsPage::class)
        ->set('data.default_daily_weight', 50)
        ->set('data.default_weekly_weight', 25)
        ->set('data.default_monthly_weight', 25)
        ->call('save')
        ->assertHasNoErrors();

    $default = BriefingPeriodWeight::whereNull('branch_id')->first();
    expect((float) $default->daily_weight)->toBe(50.0);
});

it('rejects a default weight total that is not 100%', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['edit briefing settings', 'edit briefing period weights']);
    actingAs($user);

    Livewire::test(BriefingSettingsPage::class)
        ->set('data.default_daily_weight', 50)
        ->set('data.default_weekly_weight', 25)
        ->set('data.default_monthly_weight', 20)
        ->call('save')
        ->assertHasErrors(['data.default_daily_weight']);

    // Nothing was saved.
    expect((float) (BriefingPeriodWeight::whereNull('branch_id')->first()?->daily_weight ?? 40))->toBe(40.0);
});

it('saves and resets a branch weight override', function () {
    $branch = Branch::factory()->create();
    BriefingBranchSetting::factory()->create(['branch_id' => $branch->id]);

    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['edit briefing settings', 'edit briefing period weights']);
    actingAs($user);

    Livewire::test(BriefingSettingsPage::class)
        ->set('selectedOverrideBranchId', $branch->id)
        ->set('useCustomWeight', true)
        ->set('data.override_daily_weight', 60)
        ->set('data.override_weekly_weight', 20)
        ->set('data.override_monthly_weight', 20)
        ->call('save')
        ->assertHasNoErrors();

    expect(BriefingPeriodWeight::where('branch_id', $branch->id)->exists())->toBeTrue();

    Livewire::test(BriefingSettingsPage::class)
        ->set('selectedOverrideBranchId', $branch->id)
        ->set('useCustomWeight', false)
        ->call('save')
        ->assertHasNoErrors();

    expect(BriefingPeriodWeight::where('branch_id', $branch->id)->exists())->toBeFalse();
});

it('registers and deactivates a Daily Briefing participant branch', function () {
    $this->travelTo('2026-10-13 10:00:00');

    $branch = Branch::factory()->create();
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['edit briefing settings', 'edit briefing period weights']);
    actingAs($user);

    $page = Livewire::test(BriefingSettingsPage::class)
        ->assertSee($branch->name)
        ->set('participationData.branch_id', $branch->id)
        ->call('addParticipation')
        ->assertHasNoErrors();

    $period = BriefingBranchSetting::where('branch_id', $branch->id)->firstOrFail();
    expect($period->coversDate(today()))->toBeTrue();

    $page->call('deactivateParticipation', $period->id);

    expect($period->fresh()->coversDate(today()))->toBeFalse()
        ->and($period->fresh()->ended_at->toDateString())->toBe('2026-10-13');
});

it('shows an overlap error when a participant period collides', function () {
    $branch = Branch::factory()->create();
    BriefingBranchSetting::factory()->create([
        'branch_id' => $branch->id,
        'started_at' => '2026-10-01',
        'ended_at' => null,
    ]);
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['edit briefing settings', 'edit briefing period weights']);
    actingAs($user);

    Livewire::test(BriefingSettingsPage::class)
        ->set('participationData.branch_id', $branch->id)
        ->call('addParticipation')
        ->assertHasErrors(['participationData.branch_id']);
});

it('registers a participant from the chosen start date and corrects the start of an existing one', function () {
    $this->travelTo('2026-10-13 10:00:00');

    $branch = Branch::factory()->create();
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['edit briefing settings', 'edit briefing period weights']);
    actingAs($user);

    $page = Livewire::test(BriefingSettingsPage::class)
        ->assertSet('participationData.started_at', '2026-10-13')
        ->set('participationData.branch_id', $branch->id)
        ->set('participationData.started_at', '2026-08-01')
        ->call('addParticipation')
        ->assertHasNoErrors();

    $period = BriefingBranchSetting::where('branch_id', $branch->id)->sole();
    expect($period->started_at->toDateString())->toBe('2026-08-01');

    $page->call('updateParticipationStart', $period->id, '2026-02-02')
        ->assertNotified('Tanggal mulai peserta diperbarui');

    expect($period->fresh()->started_at->toDateString())->toBe('2026-02-02');

    $page->call('updateParticipationStart', $period->id, '2026-12-01')
        ->assertNotified('Tanggal mulai tidak dapat diubah');

    expect($period->fresh()->started_at->toDateString())->toBe('2026-02-02');
});

it('does not touch an existing BriefingScore snapshot when settings are saved (no hidden recompute)', function () {
    $branch = Branch::factory()->create();
    $score = BriefingScore::create([
        'branch_id' => $branch->id,
        'year' => 2026,
        'month' => 5,
        'score' => 61.1,
        'breakdown' => ['method_version' => 2, 'passing_score' => 80],
        'computed_at' => now(),
    ]);

    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['edit briefing settings', 'edit briefing period weights']);
    actingAs($user);

    Livewire::test(BriefingSettingsPage::class)
        ->set('data.passing_score', 95)
        ->call('save')
        ->assertHasNoErrors();

    expect($score->fresh()->score)->toBe(61.1);
});

it('renders the full settings page over HTTP', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['edit briefing settings', 'edit briefing period weights', 'access backoffice']);
    actingAs($user);

    $this->get(route('filament.helpdesk.pages.briefing-settings-page'))
        ->assertOk()
        ->assertSee('Pengaturan Nilai Daily Briefing');
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
