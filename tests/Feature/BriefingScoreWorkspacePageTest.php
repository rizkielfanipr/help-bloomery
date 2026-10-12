<?php

use App\Filament\Helpdesk\Pages\BriefingScoreWorkspacePage;
use App\Models\Branch;
use App\Models\BriefingBranchSetting;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use App\Models\BriefingScore;
use App\Models\BriefingSettings;
use App\Models\BriefingTask;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * docs/daily-briefing-scoring-ui-prd.md §13/§16/§21 (Phase 8/14).
 */
function scoreFor(Branch $branch, int $year, int $month, float $score, array $overrides = []): BriefingScore
{
    return BriefingScore::create(array_merge([
        'branch_id' => $branch->id,
        'year' => $year,
        'month' => $month,
        'score' => $score,
        'breakdown' => ['method_version' => 2, 'passing_score' => 80, 'status' => $score >= 80 ? 'achieved' : 'not_achieved'],
        'computed_at' => now(),
    ], $overrides));
}

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('redirects the old /briefing-scores route (BriefingScoreResource, now deleted) to the new workspace page', function () {
    $admin = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $admin->givePermissionTo(['view briefing scores', 'access backoffice']);
    actingAs($admin);

    $this->get(route('filament.helpdesk.pages.briefing-scores'))
        ->assertRedirect(BriefingScoreWorkspacePage::getUrl());
});

it('blocks access without view briefing scores permission', function () {
    $user = User::factory()->create(['is_active' => true]);
    actingAs($user);

    expect(BriefingScoreWorkspacePage::canAccess())->toBeFalse();
});

it('renders the full page over HTTP', function () {
    $admin = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $admin->givePermissionTo(['view briefing scores', 'access backoffice']);
    actingAs($admin);

    $this->get(route('filament.helpdesk.pages.briefing-score-workspace-page'))
        ->assertOk()
        ->assertSee('Nilai Daily Briefing');
});

it('ranks scored branches by final score descending with branch name as tie-breaker', function () {
    $low = Branch::factory()->create(['name' => 'Zeta Branch']);
    $highA = Branch::factory()->create(['name' => 'Alpha Branch']);
    $highB = Branch::factory()->create(['name' => 'Beta Branch']);

    foreach ([$low, $highA, $highB] as $branch) {
        BriefingBranchSetting::factory()->create(['branch_id' => $branch->id]);
    }

    scoreFor($low, 2026, 6, 50.0);
    scoreFor($highA, 2026, 6, 90.0);
    scoreFor($highB, 2026, 6, 90.0);

    $admin = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $admin->givePermissionTo('view briefing scores');
    actingAs($admin);

    $component = Livewire::test(BriefingScoreWorkspacePage::class)->set('year', 2026)->set('month', 6);
    $ranking = $component->instance()->ranking();

    $names = $ranking['scored']->pluck('branch.name')->all();

    // Alpha and Beta tie at 90 -> alphabetical; Zeta (50) last.
    expect($names)->toBe(['Alpha Branch', 'Beta Branch', 'Zeta Branch']);
});

it('keeps a branch without a score out of the ranked list instead of showing it as zero', function () {
    $scored = Branch::factory()->create();
    $unscored = Branch::factory()->create();
    BriefingBranchSetting::factory()->create(['branch_id' => $scored->id]);
    BriefingBranchSetting::factory()->create(['branch_id' => $unscored->id]);

    scoreFor($scored, 2026, 6, 70.0);

    $admin = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $admin->givePermissionTo('view briefing scores');
    actingAs($admin);

    $ranking = Livewire::test(BriefingScoreWorkspacePage::class)->set('year', 2026)->set('month', 6)
        ->instance()->ranking();

    expect($ranking['scored'])->toHaveCount(1)
        ->and($ranking['unscored'])->toHaveCount(1)
        ->and($ranking['unscored']->first()->branch->id)->toBe($unscored->id);
});

it('restricts the workspace to accessibleBranchIds() for a non-global viewer', function () {
    $ownBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    BriefingBranchSetting::factory()->create(['branch_id' => $ownBranch->id]);
    BriefingBranchSetting::factory()->create(['branch_id' => $otherBranch->id]);

    scoreFor($ownBranch, 2026, 6, 70.0);
    scoreFor($otherBranch, 2026, 6, 90.0);

    $supervisor = User::factory()->create(['is_active' => true]);
    $supervisor->givePermissionTo('view briefing scores');
    $supervisor->syncBranchAccess([$ownBranch->id], $ownBranch->id);
    actingAs($supervisor);

    $ranking = Livewire::test(BriefingScoreWorkspacePage::class)->set('year', 2026)->set('month', 6)
        ->instance()->ranking();

    $allBranchIds = $ranking['scored']->pluck('branch.id')->merge($ranking['unscored']->pluck('branch.id'));

    expect($allBranchIds)->toContain($ownBranch->id)
        ->and($allBranchIds)->not->toContain($otherBranch->id);
});

it('does not return a detail score for a branch outside the viewer\'s access even via a crafted detailBranchId', function () {
    $otherBranch = Branch::factory()->create();
    BriefingBranchSetting::factory()->create(['branch_id' => $otherBranch->id]);
    scoreFor($otherBranch, 2026, 6, 90.0);

    $ownBranch = Branch::factory()->create();
    $supervisor = User::factory()->create(['is_active' => true]);
    $supervisor->givePermissionTo('view briefing scores');
    $supervisor->syncBranchAccess([$ownBranch->id], $ownBranch->id);
    actingAs($supervisor);

    $detail = Livewire::test(BriefingScoreWorkspacePage::class)
        ->set('year', 2026)->set('month', 6)
        ->call('viewDetail', $otherBranch->id)
        ->instance()->detailScore();

    expect($detail)->toBeNull();
});

it('computes summary counts from the ranked scores only', function () {
    $achieve = Branch::factory()->create();
    $notAchieve = Branch::factory()->create();
    $unscored = Branch::factory()->create();
    foreach ([$achieve, $notAchieve, $unscored] as $branch) {
        BriefingBranchSetting::factory()->create(['branch_id' => $branch->id]);
    }
    scoreFor($achieve, 2026, 6, 90.0);
    scoreFor($notAchieve, 2026, 6, 50.0);

    $admin = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $admin->givePermissionTo('view briefing scores');
    actingAs($admin);

    $summary = Livewire::test(BriefingScoreWorkspacePage::class)->set('year', 2026)->set('month', 6)
        ->instance()->summary();

    expect($summary['count'])->toBe(2)
        ->and($summary['achieve'])->toBe(1)
        ->and($summary['notAchieve'])->toBe(1)
        ->and($summary['unscored'])->toBe(1)
        ->and($summary['average'])->toBe(70.0);
});

it('rejects starting a recompute for a user without edit briefing scores', function () {
    $branch = Branch::factory()->create();
    BriefingBranchSetting::factory()->create(['branch_id' => $branch->id]);

    $user = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $user->givePermissionTo('view briefing scores');
    actingAs($user);

    Livewire::test(BriefingScoreWorkspacePage::class)
        ->set('year', 2026)->set('month', 6)
        ->call('startRecalculation')
        ->assertForbidden();
});

it('recomputes scores for the branches in the current filter via the batched queue', function () {
    BriefingSettings::instance()->update(['scoring_started_at' => '2020-01-01']);

    $branch = Branch::factory()->create();
    BriefingBranchSetting::factory()->create(['branch_id' => $branch->id]);

    $task = BriefingTask::create(['key' => 'workspace_recompute_task', 'label' => 'Task', 'period' => 'daily', 'submission_type' => 'camera_only', 'is_active' => true, 'include_in_score' => true, 'sort_order' => 1]);
    $staff = User::factory()->create(['is_active' => true, 'branch_id' => $branch->id]);
    $record = BriefingRecord::create(['user_id' => $staff->id, 'branch_id' => $branch->id, 'period' => 'daily', 'record_date' => '2026-06-01']);
    BriefingItem::create(['briefing_record_id' => $record->id, 'task_key' => $task->key, 'is_completed' => true, 'completed_at' => '2026-06-01', 'review_status' => 'approved']);

    $admin = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $admin->givePermissionTo(['view briefing scores', 'edit briefing scores']);
    actingAs($admin);

    $component = Livewire::test(BriefingScoreWorkspacePage::class)
        ->set('year', 2026)->set('month', 6)
        ->call('startRecalculation');

    expect($component->get('recalculationTotal'))->toBe(1);

    $component->call('processRecalculationBatch');

    expect(BriefingScore::where('branch_id', $branch->id)->where('year', 2026)->where('month', 6)->exists())->toBeTrue()
        ->and($component->get('recalculationQueue'))->toBe([]);
});
