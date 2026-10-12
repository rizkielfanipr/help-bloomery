<?php

use App\Filament\Casual\Pages\BriefingScorePage;
use App\Models\Branch;
use App\Models\BriefingScore;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * docs/daily-briefing-scoring-ui-prd.md §16/Phase 11. Contract change: this page previously had
 * no canAccess() at all.
 */
beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('blocks access without the view briefing scores permission', function () {
    $user = User::factory()->create(['is_active' => true]);
    actingAs($user);

    expect(BriefingScorePage::canAccess())->toBeFalse();
});

it('allows access for a STORE_STAFF user (granted view briefing scores by default)', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('STORE_STAFF');
    actingAs($user);

    expect(BriefingScorePage::canAccess())->toBeTrue();
});

it('only shows scores for the user\'s own branch', function () {
    $ownBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();

    BriefingScore::create(['branch_id' => $ownBranch->id, 'year' => 2026, 'month' => 6, 'score' => 70, 'breakdown' => ['method_version' => 2, 'passing_score' => 80], 'computed_at' => now()]);
    BriefingScore::create(['branch_id' => $otherBranch->id, 'year' => 2026, 'month' => 6, 'score' => 95, 'breakdown' => ['method_version' => 2, 'passing_score' => 80], 'computed_at' => now()]);

    $user = User::factory()->create(['is_active' => true, 'branch_id' => $ownBranch->id]);
    $user->assignRole('STORE_STAFF');
    actingAs($user);

    $scores = Livewire::test(BriefingScorePage::class)->instance()->scores();

    expect($scores)->toHaveCount(1)
        ->and($scores->first()->branch_id)->toBe($ownBranch->id);
});

it('shows an empty state for a user without a branch', function () {
    $user = User::factory()->create(['is_active' => true, 'branch_id' => null]);
    $user->assignRole('STORE_STAFF');
    actingAs($user);

    expect(Livewire::test(BriefingScorePage::class)->instance()->scores())->toBeEmpty();
});
