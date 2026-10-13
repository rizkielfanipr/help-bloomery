<?php

use App\Actions\Briefing\SaveBriefingBranchParticipationAction;
use App\Models\Branch;
use App\Models\BriefingBranchSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
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

it('registers a participant from a past start date but never from a future one', function () {
    $this->travelTo('2026-10-13 10:00:00');

    $actor = User::factory()->create();
    $actor->givePermissionTo('edit briefing settings');
    $action = app(SaveBriefingBranchParticipationAction::class);

    $period = $action->execute($actor, Branch::factory()->create()->id, '2026-05-01');

    expect($period->started_at->toDateString())->toBe('2026-05-01')
        ->and($period->coversDate(CarbonImmutable::parse('2026-06-15')))->toBeTrue();

    expect(fn () => $action->execute($actor, Branch::factory()->create()->id, '2026-10-14'))
        ->toThrow(ValidationException::class);
});

it('rejects a backdated start that would swallow a later period of the same branch', function () {
    $this->travelTo('2026-10-13 10:00:00');

    $actor = User::factory()->create();
    $actor->givePermissionTo('edit briefing settings');
    $branch = Branch::factory()->create();
    BriefingBranchSetting::factory()->create(['branch_id' => $branch->id, 'started_at' => '2026-07-01', 'ended_at' => '2026-08-31']);
    $action = app(SaveBriefingBranchParticipationAction::class);

    expect(fn () => $action->execute($actor, $branch->id, '2026-06-01'))->toThrow(ValidationException::class);

    expect($action->execute($actor, $branch->id, '2026-09-01')->started_at->toDateString())->toBe('2026-09-01');
});

it('moves the start of an existing period back and audits who changed it', function () {
    $this->travelTo('2026-10-13 10:00:00');

    $actor = User::factory()->create();
    $actor->givePermissionTo('edit briefing settings');
    $period = BriefingBranchSetting::factory()->create(['started_at' => '2026-10-13', 'ended_at' => null]);

    app(SaveBriefingBranchParticipationAction::class)->updateStartDate($actor, $period->id, '2026-01-05');

    expect($period->fresh()->started_at->toDateString())->toBe('2026-01-05')
        ->and($period->fresh()->updated_by)->toBe($actor->id);
});

it('refuses a start date change that is in the future, overlaps another period, or is unauthorized', function (string $case) {
    $this->travelTo('2026-10-13 10:00:00');

    $actor = User::factory()->create();
    $actor->givePermissionTo('edit briefing settings');
    $branch = Branch::factory()->create();
    BriefingBranchSetting::factory()->create(['branch_id' => $branch->id, 'started_at' => '2026-03-01', 'ended_at' => '2026-04-30']);
    $period = BriefingBranchSetting::factory()->create(['branch_id' => $branch->id, 'started_at' => '2026-10-01', 'ended_at' => null]);

    [$user, $date] = match ($case) {
        'future' => [$actor, '2026-10-20'],
        'overlap' => [$actor, '2026-04-15'],
        'unauthorized' => [User::factory()->create(), '2026-09-01'],
    };

    expect(fn () => app(SaveBriefingBranchParticipationAction::class)->updateStartDate($user, $period->id, $date))
        ->toThrow(ValidationException::class);

    expect($period->fresh()->started_at->toDateString())->toBe('2026-10-01');
})->with(['future', 'overlap', 'unauthorized']);
