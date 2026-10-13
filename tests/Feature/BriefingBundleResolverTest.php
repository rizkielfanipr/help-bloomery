<?php

use App\Enums\BriefingBundleStatus;
use App\Enums\BriefingPeriod;
use App\Enums\BriefingSubmissionType;
use App\Enums\BriefingTransitionSource;
use App\Exceptions\Briefing\BriefingBundleConflictException;
use App\Exceptions\Briefing\BriefingBundleUnavailableException;
use App\Models\Branch;
use App\Models\BriefingBranchSetting;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use App\Models\BriefingRecordRequirement;
use App\Models\BriefingTask;
use App\Models\User;
use App\Services\BriefingBundleCompletenessService;
use App\Services\BriefingBundleResolver;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;

/**
 * docs/daily-briefing-bundle-approval-prd.md Phase 3: canonical bundle resolution and the
 * completeness rule, independent of the Employee App UI (not wired in yet).
 */
function resolverBranch(): Branch
{
    $branch = Branch::factory()->create();
    BriefingBranchSetting::factory()->create(['branch_id' => $branch->id]);

    return $branch;
}

function resolverStaff(Branch $branch): User
{
    $user = User::factory()->create(['is_active' => true, 'branch_id' => $branch->id]);
    $user->assignRole('STORE_STAFF');

    return $user;
}

function resolverTask(string $key, array $overrides = []): BriefingTask
{
    return BriefingTask::create(array_merge([
        'key' => $key,
        'label' => 'Task '.$key,
        'period' => 'daily',
        'submission_type' => 'camera_only',
        'is_active' => true,
        'include_in_score' => true,
        'sort_order' => 1,
    ], $overrides));
}

function resolveBundle(Branch $branch, BriefingPeriod $period, string $date, ?User $creator = null): BriefingRecord
{
    return app(BriefingBundleResolver::class)->findOrCreate(
        $branch,
        $period,
        CarbonImmutable::parse($date),
        $creator ?? resolverStaff($branch),
    );
}

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Cache::flush();
    $this->travelTo(CarbonImmutable::parse('2026-10-14 10:00:00'));
});

describe('bundle resolver', function () {
    it('creates one draft bundle with a normalized key, a requirement snapshot, an audit row, and no items', function () {
        $branch = resolverBranch();
        resolverTask('resolver_daily_a', ['sort_order' => 2]);
        resolverTask('resolver_daily_b', ['submission_type' => 'text_only', 'sort_order' => 1]);
        $staff = resolverStaff($branch);

        $bundle = resolveBundle($branch, BriefingPeriod::Daily, '2026-10-14', $staff);

        expect($bundle->status)->toBe(BriefingBundleStatus::Draft)
            ->and($bundle->branch_id)->toBe($branch->id)
            ->and($bundle->user_id)->toBe($staff->id)
            ->and($bundle->period_start->toDateString())->toBe('2026-10-14')
            ->and($bundle->period_end->toDateString())->toBe('2026-10-14')
            ->and($bundle->record_date->toDateString())->toBe('2026-10-14')
            ->and($bundle->requirements->pluck('task_key')->sort()->values()->all())->toBe(['resolver_daily_a', 'resolver_daily_b'])
            ->and($bundle->items()->count())->toBe(0)
            ->and($bundle->transitions->sole()->source)->toBe(BriefingTransitionSource::UserAction)
            ->and($bundle->transitions->sole()->actor_id)->toBe($staff->id);
    });

    it('resolves every date in the same period to one bundle', function (BriefingPeriod $period, string $firstDate, string $secondDate, string $expectedStart) {
        $branch = resolverBranch();
        resolverTask('resolver_'.$period->value, ['period' => $period->value]);
        $staff = resolverStaff($branch);

        $first = resolveBundle($branch, $period, $firstDate, $staff);
        $second = resolveBundle($branch, $period, $secondDate, $staff);

        expect($second->is($first))->toBeTrue()
            ->and($first->period_start->toDateString())->toBe($expectedStart)
            ->and(BriefingRecord::count())->toBe(1);
    })->with([
        'weekly Wednesday and Saturday' => [BriefingPeriod::Weekly, '2026-10-14', '2026-10-17', '2026-10-12'],
        'monthly early and late in the month' => [BriefingPeriod::Monthly, '2026-10-02', '2026-10-14', '2026-10-01'],
    ]);

    it('gives two STORE_STAFF of the same branch the same canonical bundle', function () {
        $branch = resolverBranch();
        resolverTask('resolver_shared');

        $first = resolveBundle($branch, BriefingPeriod::Daily, '2026-10-14', resolverStaff($branch));
        $second = resolveBundle($branch, BriefingPeriod::Daily, '2026-10-14', resolverStaff($branch));

        expect($second->is($first))->toBeTrue()
            ->and(BriefingRecord::count())->toBe(1)
            ->and($first->transitions()->count())->toBe(1);
    });

    it('re-checks for an existing bundle inside the lock so a repeated request never creates a second one', function () {
        $branch = resolverBranch();
        resolverTask('resolver_repeat');
        $staff = resolverStaff($branch);

        foreach (range(1, 3) as $attempt) {
            resolveBundle($branch, BriefingPeriod::Daily, '2026-10-14', $staff);
        }

        expect(BriefingRecord::count())->toBe(1)
            ->and(BriefingRecordRequirement::count())->toBe(1);
    });

    it('keys the creation lock on the normalized period_start, not the raw date', function () {
        $resolver = app(BriefingBundleResolver::class);

        expect($resolver->lockKey(7, BriefingPeriod::Weekly, CarbonImmutable::parse('2026-10-12')))
            ->toBe('briefing-bundle:7:weekly:2026-10-12');
    });

    it('refuses a branch that is not a participant and creates nothing', function () {
        $branch = Branch::factory()->create();
        resolverTask('resolver_non_participant');
        $staff = resolverStaff($branch);

        expect(fn () => resolveBundle($branch, BriefingPeriod::Daily, '2026-10-14', $staff))
            ->toThrow(BriefingBundleUnavailableException::class);

        expect(BriefingRecord::count())->toBe(0);
    });

    it('refuses a creator who is not a participant of that branch', function (string $who) {
        $branch = resolverBranch();
        resolverTask('resolver_actor_check');

        $creator = match ($who) {
            'staff of another branch' => resolverStaff(resolverBranch()),
            'supervisor of this branch' => tap(User::factory()->create(['is_active' => true, 'branch_id' => $branch->id]))->assignRole('SUPERVISOR_STORE'),
            'inactive staff of this branch' => tap(resolverStaff($branch))->update(['is_active' => false]),
        };

        expect(fn () => resolveBundle($branch, BriefingPeriod::Daily, '2026-10-14', $creator))
            ->toThrow(fn (BriefingBundleUnavailableException $e) => expect($e->reason)->toBe('actor_not_participant'));

        expect(BriefingRecord::count())->toBe(0);
    })->with(['staff of another branch', 'supervisor of this branch', 'inactive staff of this branch']);

    it('snapshots only the branch\'s own active tasks when it overrides the global ones', function () {
        $branch = resolverBranch();
        resolverTask('resolver_global');
        resolverTask('resolver_branch_own', ['branch_id' => $branch->id]);
        resolverTask('resolver_branch_inactive', ['branch_id' => $branch->id, 'is_active' => false]);

        $bundle = resolveBundle($branch, BriefingPeriod::Daily, '2026-10-14');

        expect($bundle->requirements->pluck('task_key')->all())->toBe(['resolver_branch_own']);
    });

    it('falls back to the active global tasks and skips inactive ones', function () {
        $branch = resolverBranch();
        resolverTask('resolver_global_active');
        resolverTask('resolver_global_inactive', ['is_active' => false]);

        $bundle = resolveBundle($branch, BriefingPeriod::Daily, '2026-10-14');

        expect($bundle->requirements->pluck('task_key')->all())->toBe(['resolver_global_active']);
    });

    it('creates no bundle when nothing is assigned for the period', function () {
        $branch = resolverBranch();
        resolverTask('resolver_only_daily');

        expect(fn () => resolveBundle($branch, BriefingPeriod::Monthly, '2026-10-14'))
            ->toThrow(fn (BriefingBundleUnavailableException $e) => expect($e->reason)->toBe('nothing_assigned'));

        expect(BriefingRecord::count())->toBe(0);
    });

    it('lets the database refuse a second bundle on the same business key', function () {
        $branch = resolverBranch();
        $attributes = ['period_start' => '2026-10-14', 'status' => BriefingBundleStatus::Draft->value];
        BriefingRecord::factory()->forBranch($branch)->onDate('2026-10-14')->create($attributes);

        expect(fn () => BriefingRecord::factory()->forBranch($branch)->onDate('2026-10-14')->create($attributes))
            ->toThrow(UniqueConstraintViolationException::class);

        expect(app(BriefingBundleResolver::class)->find($branch->id, BriefingPeriod::Daily, CarbonImmutable::parse('2026-10-14')))->not->toBeNull();
    });

    it('still allows any number of legacy rows without a period_start next to a bundle', function () {
        $branch = resolverBranch();
        BriefingRecord::factory()->forBranch($branch)->onDate('2026-10-14')->create(['period_start' => '2026-10-14', 'status' => BriefingBundleStatus::Draft->value]);
        BriefingRecord::factory()->forBranch($branch)->onDate('2026-10-14')->count(2)->create();

        expect(BriefingRecord::count())->toBe(3);
    });
});

describe('legacy rows during the transition', function () {
    it('adopts a single legacy row sitting exactly on the business key and audits it as a reconciliation', function () {
        $branch = resolverBranch();
        resolverTask('resolver_legacy_exact');
        $legacy = BriefingRecord::factory()->forBranch($branch)->onDate('2026-10-14')->create();

        $bundle = resolveBundle($branch, BriefingPeriod::Daily, '2026-10-14');

        expect($bundle->is($legacy))->toBeTrue()
            ->and(BriefingRecord::count())->toBe(1)
            ->and($bundle->status)->toBe(BriefingBundleStatus::Draft)
            ->and($bundle->period_start->toDateString())->toBe('2026-10-14')
            ->and($bundle->user_id)->toBe($legacy->user_id)
            ->and($bundle->transitions->sole()->source)->toBe(BriefingTransitionSource::SystemReconciliation)
            ->and($bundle->transitions->sole()->metadata)->toBe(['adopted_legacy_record' => true, 'legacy_record_date' => '2026-10-14']);
    });

    it('leaves a mid-week legacy weekly row alone for the grouping command and starts a clean Monday bundle', function () {
        $branch = resolverBranch();
        resolverTask('resolver_legacy_weekly', ['period' => 'weekly']);
        $midWeekLegacy = BriefingRecord::factory()->forBranch($branch)->forPeriod(BriefingPeriod::Weekly, '2026-10-13')->create();

        $bundle = resolveBundle($branch, BriefingPeriod::Weekly, '2026-10-14');

        expect($bundle->is($midWeekLegacy))->toBeFalse()
            ->and($bundle->period_start->toDateString())->toBe('2026-10-12')
            ->and($midWeekLegacy->refresh()->isLegacyUngrouped())->toBeTrue()
            ->and($midWeekLegacy->period_start)->toBeNull();
    });

    it('refuses to choose between several legacy rows on the business key and changes none of them', function () {
        $branch = resolverBranch();
        resolverTask('resolver_legacy_duplicate');
        BriefingRecord::factory()->forBranch($branch)->onDate('2026-10-14')->count(2)->create();

        expect(fn () => resolveBundle($branch, BriefingPeriod::Daily, '2026-10-14'))
            ->toThrow(BriefingBundleConflictException::class);

        expect(BriefingRecord::count())->toBe(2)
            ->and(BriefingRecord::whereNotNull('status')->count())->toBe(0);
    });
});

describe('bundle completeness', function () {
    it('decides whether one submission satisfies its submission type', function (BriefingSubmissionType $type, array $item, bool $satisfied) {
        $model = BriefingItem::factory()->make(array_merge([
            'photo_paths' => null,
            'notes' => null,
            'is_completed' => true,
            'completed_at' => now(),
        ], $item));

        expect(app(BriefingBundleCompletenessService::class)->itemSatisfies($type, $model))->toBe($satisfied);
    })->with([
        'camera with a photo' => [BriefingSubmissionType::CameraOnly, ['photo_paths' => ['a.jpg']], true],
        'camera without a photo' => [BriefingSubmissionType::CameraOnly, [], false],
        'photo with a photo' => [BriefingSubmissionType::Photo, ['photo_paths' => ['a.jpg']], true],
        'photo and text missing the text' => [BriefingSubmissionType::PhotoAndText, ['photo_paths' => ['a.jpg']], false],
        'photo and text missing the photo' => [BriefingSubmissionType::PhotoAndText, ['notes' => 'Catatan'], false],
        'photo and text with both' => [BriefingSubmissionType::PhotoAndText, ['photo_paths' => ['a.jpg'], 'notes' => 'Catatan'], true],
        'text with notes' => [BriefingSubmissionType::TextOnly, ['notes' => 'Catatan'], true],
        'text with only whitespace' => [BriefingSubmissionType::TextOnly, ['notes' => '   '], false],
        'checkbox ticked with a timestamp' => [BriefingSubmissionType::Checkbox, [], true],
        'checkbox not ticked' => [BriefingSubmissionType::Checkbox, ['is_completed' => false], false],
        'checkbox ticked without completed_at' => [BriefingSubmissionType::Checkbox, ['completed_at' => null], false],
        'valid evidence still flagged for correction' => [BriefingSubmissionType::CameraOnly, ['photo_paths' => ['a.jpg'], 'requires_correction' => true], false],
    ]);

    it('counts required points against the snapshot and ignores optional ones and items outside it', function () {
        $bundle = BriefingRecord::factory()->create();
        BriefingRecordRequirement::factory()->for($bundle, 'record')->create(['task_key' => 'req_done']);
        BriefingRecordRequirement::factory()->for($bundle, 'record')->create(['task_key' => 'req_missing']);
        BriefingRecordRequirement::factory()->for($bundle, 'record')->optional()->create(['task_key' => 'opt_missing']);
        BriefingItem::factory()->for($bundle, 'record')->forTask('req_done')->create();
        BriefingItem::factory()->for($bundle, 'record')->forTask('not_in_snapshot')->create();

        $completeness = app(BriefingBundleCompletenessService::class)->evaluate($bundle);

        expect($completeness->required)->toBe(2)
            ->and($completeness->completed)->toBe(1)
            ->and($completeness->missingTaskKeys)->toBe(['req_missing'])
            ->and($completeness->isComplete())->toBeFalse();
    });

    it('is complete once every required point is valid, even with optional points left empty', function () {
        $bundle = BriefingRecord::factory()->create();
        BriefingRecordRequirement::factory()->for($bundle, 'record')->create(['task_key' => 'req_only']);
        BriefingRecordRequirement::factory()->for($bundle, 'record')->optional()->create(['task_key' => 'opt_skipped']);
        BriefingItem::factory()->for($bundle, 'record')->forTask('req_only')->create();

        expect(app(BriefingBundleCompletenessService::class)->evaluate($bundle)->isComplete())->toBeTrue();
    });

    it('never treats a bundle without required points as complete', function () {
        $bundle = BriefingRecord::factory()->create();

        $completeness = app(BriefingBundleCompletenessService::class)->evaluate($bundle);

        expect($completeness->required)->toBe(0)
            ->and($completeness->isComplete())->toBeFalse();
    });

    it('judges a past bundle by its snapshot even after the task master changes type', function () {
        $branch = resolverBranch();
        $task = resolverTask('resolver_snapshot_type', ['submission_type' => 'camera_only']);
        $bundle = resolveBundle($branch, BriefingPeriod::Daily, '2026-10-14');
        BriefingItem::factory()->for($bundle, 'record')->forTask($task->key)->create(['notes' => null]);

        $task->update(['submission_type' => 'photo_and_text']);

        expect(app(BriefingBundleCompletenessService::class)->evaluate($bundle->fresh())->isComplete())->toBeTrue();
    });
});
