<?php

use App\Actions\Briefing\SaveBriefingGlobalSettingsAction;
use App\Actions\Briefing\SaveBriefingPeriodWeightAction;
use App\Models\Branch;
use App\Models\BriefingPeriodWeight;
use App\Models\BriefingScore;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * docs/daily-briefing-scoring-ui-prd.md §12/§16/§21 (Phase 4/14).
 */
it('saves global settings for an authorized user', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('edit briefing settings');

    $settings = app(SaveBriefingGlobalSettingsAction::class)->execute($user, [
        'scoring_started_at' => '2026-01-01',
        'passing_score' => 85,
        'auto_reject_after_days' => 5,
        'auto_reject_reason' => 'Alasan baru :days hari.',
    ]);

    expect($settings->fresh()->passing_score)->toBe(85.0)
        ->and($settings->fresh()->auto_reject_after_days)->toBe(5);
});

it('rejects a global settings save from a user without edit briefing settings', function () {
    $user = User::factory()->create();

    expect(fn () => app(SaveBriefingGlobalSettingsAction::class)->execute($user, [
        'scoring_started_at' => '2026-01-01',
        'passing_score' => 85,
        'auto_reject_after_days' => 5,
        'auto_reject_reason' => 'x',
    ]))->toThrow(ValidationException::class);
});

it('rejects a passing_score outside 0-100', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('edit briefing settings');

    expect(fn () => app(SaveBriefingGlobalSettingsAction::class)->execute($user, [
        'scoring_started_at' => '2026-01-01',
        'passing_score' => 150,
        'auto_reject_after_days' => 5,
        'auto_reject_reason' => 'x',
    ]))->toThrow(ValidationException::class);
});

it('does not touch existing BriefingScore rows when settings are saved (no hidden recompute)', function () {
    $branch = Branch::factory()->create();
    $score = BriefingScore::create([
        'branch_id' => $branch->id,
        'year' => 2026,
        'month' => 5,
        'score' => 73.5,
        'breakdown' => ['method_version' => 2, 'passing_score' => 80],
        'computed_at' => now(),
    ]);

    $user = User::factory()->create();
    $user->givePermissionTo('edit briefing settings');

    app(SaveBriefingGlobalSettingsAction::class)->execute($user, [
        'scoring_started_at' => '2026-01-01',
        'passing_score' => 95,
        'auto_reject_after_days' => 5,
        'auto_reject_reason' => 'x',
    ]);

    expect($score->fresh()->score)->toBe(73.5)
        ->and($score->fresh()->breakdown['passing_score'])->toBe(80);
});

it('saves the default period weight row (branch_id null)', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('edit briefing period weights');

    app(SaveBriefingPeriodWeightAction::class)->execute($user, null, [
        'daily_weight' => 50, 'weekly_weight' => 25, 'monthly_weight' => 25,
    ]);

    $default = BriefingPeriodWeight::whereNull('branch_id')->first();
    expect((float) $default->daily_weight)->toBe(50.0);
});

it('rejects a weight total that is not 100%', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('edit briefing period weights');

    expect(fn () => app(SaveBriefingPeriodWeightAction::class)->execute($user, null, [
        'daily_weight' => 50, 'weekly_weight' => 25, 'monthly_weight' => 20,
    ]))->toThrow(ValidationException::class);
});

it('saves and then resets a branch override back to default', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo('edit briefing period weights');

    $action = app(SaveBriefingPeriodWeightAction::class);
    $action->execute($user, $branch->id, ['daily_weight' => 60, 'weekly_weight' => 20, 'monthly_weight' => 20]);

    expect(BriefingPeriodWeight::where('branch_id', $branch->id)->exists())->toBeTrue();

    $action->reset($user, $branch->id);

    expect(BriefingPeriodWeight::where('branch_id', $branch->id)->exists())->toBeFalse();
});

it('rejects a period weight save from a user without edit briefing period weights', function () {
    $user = User::factory()->create();

    expect(fn () => app(SaveBriefingPeriodWeightAction::class)->execute($user, null, [
        'daily_weight' => 50, 'weekly_weight' => 25, 'monthly_weight' => 25,
    ]))->toThrow(ValidationException::class);
});
