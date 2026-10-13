<?php

use App\Actions\Briefing\SaveBriefingBranchParticipationAction;
use App\Models\Branch;
use App\Models\BriefingBranchSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('creates an audited branch participation period', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('edit briefing settings');
    $branch = Branch::factory()->create();

    $this->travelTo('2026-10-13 10:00:00');

    $period = app(SaveBriefingBranchParticipationAction::class)->execute($actor, $branch->id);

    expect($period->branch_id)->toBe($branch->id)
        ->and($period->is_enabled)->toBeTrue()
        ->and($period->started_at->toDateString())->toBe('2026-10-13')
        ->and($period->ended_at)->toBeNull()
        ->and($period->created_by)->toBe($actor->id)
        ->and($period->updated_by)->toBe($actor->id);
});

it('rejects unauthorized participation changes', function () {
    $branch = Branch::factory()->create();

    expect(fn () => app(SaveBriefingBranchParticipationAction::class)->execute(
        User::factory()->create(),
        $branch->id,
    ))->toThrow(ValidationException::class);
});

it('rejects an already active branch but accepts a branch whose previous period has ended', function () {
    $this->travelTo('2026-10-13 10:00:00');

    $actor = User::factory()->create();
    $actor->givePermissionTo('edit briefing settings');
    $branch = Branch::factory()->create();
    $action = app(SaveBriefingBranchParticipationAction::class);

    BriefingBranchSetting::factory()->create([
        'branch_id' => $branch->id,
        'started_at' => '2026-10-01',
        'ended_at' => null,
    ]);

    expect(fn () => $action->execute($actor, $branch->id))->toThrow(ValidationException::class);

    BriefingBranchSetting::where('branch_id', $branch->id)->update(['ended_at' => '2026-10-12']);
    $followingPeriod = $action->execute($actor, $branch->id);

    expect($followingPeriod)->toBeInstanceOf(BriefingBranchSetting::class);
});

it('deactivates current participation without erasing its historical coverage', function () {
    $this->travelTo('2026-10-13 10:00:00');

    $actor = User::factory()->create();
    $actor->givePermissionTo('edit briefing settings');
    $period = BriefingBranchSetting::factory()->create([
        'started_at' => '2026-10-01',
        'ended_at' => null,
    ]);

    app(SaveBriefingBranchParticipationAction::class)->deactivate($actor, $period->id);

    expect($period->fresh()->is_enabled)->toBeTrue()
        ->and($period->fresh()->ended_at->toDateString())->toBe('2026-10-12');
});
