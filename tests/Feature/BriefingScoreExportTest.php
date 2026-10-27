<?php

use App\Models\Branch;
use App\Models\BriefingScore;
use App\Models\User;

use function Pest\Laravel\actingAs;

/**
 * docs/products/daily-briefing/scoring-ui/prd.md §17/§16/Phase 10-11. No test previously existed for
 * either export endpoint.
 */
it('rejects the admin score Excel export for a branch_id outside the requester\'s access', function () {
    $ownBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();

    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo('view briefing scores');
    $user->syncBranchAccess([$ownBranch->id], $ownBranch->id);
    actingAs($user);

    $this->get(route('helpdesk.exports.briefing-scores', ['branch_id' => $otherBranch->id, 'year' => 2026]))
        ->assertForbidden();
});

it('allows the admin score PDF export for an accessible branch and renders the breakdown', function () {
    $branch = Branch::factory()->create();
    BriefingScore::create([
        'branch_id' => $branch->id,
        'year' => 2026,
        'month' => 6,
        'score' => 92.5,
        'breakdown' => [
            'method_version' => 2,
            'passing_score' => 80,
            'is_provisional' => false,
            'periods' => [
                'daily' => ['applicable' => true, 'approved' => 27, 'expected' => 30, 'rate' => 90.0, 'configured_weight' => 40, 'effective_weight' => 100, 'contribution' => 90.0, 'tasks' => [['key' => 't1', 'label' => 'Task 1', 'approved' => 27, 'expected' => 30, 'rate' => 90.0]]],
                'weekly' => ['applicable' => false],
                'monthly' => ['applicable' => false],
            ],
        ],
        'computed_at' => now(),
    ]);

    $user = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $user->givePermissionTo('view briefing scores');
    actingAs($user);

    $this->get(route('helpdesk.exports.briefing-scores', ['format' => 'pdf', 'branch_id' => $branch->id, 'year' => 2026, 'month' => 6]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('produces an empty-but-valid Excel export when there is no data', function () {
    $user = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $user->givePermissionTo('view briefing scores');
    actingAs($user);

    $this->get(route('helpdesk.exports.briefing-scores', ['year' => 2099]))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('renders a score computed under the old method_version 1 breakdown shape without error', function () {
    $branch = Branch::factory()->create();
    BriefingScore::create([
        'branch_id' => $branch->id,
        'year' => 2026,
        'month' => 5,
        'score' => 88.0,
        // Old flat shape: breakdown['daily'] directly, no 'periods' wrapper, no passing_score/method_version.
        'breakdown' => [
            'daily' => ['label' => 'Harian', 'configured_weight' => 40, 'effective_weight' => 100, 'score' => 88.0, 'tasks' => [['label' => 'Task', 'approved' => 10, 'expected' => 10, 'rate' => 100.0]]],
        ],
        'computed_at' => now(),
    ]);

    $user = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $user->givePermissionTo('view briefing scores');
    actingAs($user);

    $this->get(route('helpdesk.exports.briefing-scores', ['format' => 'pdf', 'branch_id' => $branch->id, 'year' => 2026, 'month' => 5]))
        ->assertOk();
});

it('lets a STORE_STAFF download their own branch\'s PDF', function () {
    $branch = Branch::factory()->create();
    BriefingScore::create([
        'branch_id' => $branch->id,
        'year' => 2026,
        'month' => 6,
        'score' => 92.5,
        'breakdown' => ['method_version' => 2, 'passing_score' => 80, 'periods' => []],
        'computed_at' => now(),
    ]);

    $user = User::factory()->create(['is_active' => true, 'branch_id' => $branch->id]);
    $user->givePermissionTo('view briefing scores');
    actingAs($user);

    $this->get(route('casual.exports.briefing-score-pdf', ['year' => 2026, 'month' => 6]))
        ->assertOk();
});

it('rejects the employee PDF for a user without the view briefing scores permission', function () {
    $branch = Branch::factory()->create();
    BriefingScore::create(['branch_id' => $branch->id, 'year' => 2026, 'month' => 6, 'score' => 92.5, 'breakdown' => [], 'computed_at' => now()]);

    $user = User::factory()->create(['is_active' => true, 'branch_id' => $branch->id]);
    actingAs($user);

    $this->get(route('casual.exports.briefing-score-pdf', ['year' => 2026, 'month' => 6]))
        ->assertForbidden();
});
