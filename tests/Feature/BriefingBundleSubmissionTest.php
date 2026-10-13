<?php

use App\Actions\Briefing\BriefingSubmissionAction;
use App\Actions\Briefing\BriefingSubmissionResult;
use App\Enums\BriefingBundleStatus;
use App\Enums\BriefingPeriod;
use App\Enums\BriefingReviewStatus;
use App\Filament\Casual\Pages\DailyBriefingPage;
use App\Models\Branch;
use App\Models\BriefingBranchSetting;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use App\Models\BriefingTask;
use App\Models\User;
use App\Services\BriefingBundleResolver;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;

/**
 * docs/daily-briefing-bundle-approval-prd.md Phase 4: the Employee App writes into the branch's one
 * canonical bundle, records each contributor, completes every submission type (checkbox included),
 * and only hands the bundle to the supervisor once it is complete.
 */
function submissionBranch(): Branch
{
    $branch = Branch::factory()->create();
    BriefingBranchSetting::factory()->create(['branch_id' => $branch->id]);

    return $branch;
}

function submissionStaff(Branch $branch): User
{
    $user = User::factory()->create(['is_active' => true, 'branch_id' => $branch->id]);
    $user->assignRole('STORE_STAFF');

    return $user;
}

function submissionTask(string $key, array $overrides = []): BriefingTask
{
    return BriefingTask::create(array_merge([
        'key' => $key,
        'label' => 'Poin '.$key,
        'period' => 'daily',
        'submission_type' => 'camera_only',
        'is_active' => true,
        'include_in_score' => true,
        'deadline_enabled' => false,
        'sort_order' => 1,
    ], $overrides));
}

function submit(User $actor, BriefingTask $task, array $payload = ['photo_paths' => ['briefing-photos/a.jpg'], 'notes' => 'Catatan']): BriefingSubmissionResult
{
    return app(BriefingSubmissionAction::class)->execute($actor, $task, $payload, now());
}

function bundleFor(Branch $branch, BriefingPeriod $period = BriefingPeriod::Daily): ?BriefingRecord
{
    return app(BriefingBundleResolver::class)->find($branch->id, $period, now());
}

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Cache::flush();
    $this->travelTo(CarbonImmutable::parse('2026-10-14 10:00:00'));
});

describe('submission action', function () {
    it('completes every submission type with completion and contributor fields', function (string $type, array $payload) {
        $branch = submissionBranch();
        $staff = submissionStaff($branch);
        $task = submissionTask('type_'.$type, ['submission_type' => $type]);

        $result = submit($staff, $task, $payload);

        $item = $result->item->refresh();

        expect($result->successful)->toBeTrue()
            ->and($item->is_completed)->toBeTrue()
            ->and($item->completed_at)->not->toBeNull()
            ->and($item->submitted_by)->toBe($staff->id)
            ->and($item->submitted_at)->not->toBeNull()
            ->and($item->record->branch_id)->toBe($branch->id);
    })->with([
        'camera' => ['camera_only', ['photo_paths' => ['a.jpg']]],
        'photo' => ['photo', ['photo_paths' => ['a.jpg']]],
        'photo and text' => ['photo_and_text', ['photo_paths' => ['a.jpg'], 'notes' => 'Catatan']],
        'text' => ['text_only', ['notes' => 'Catatan']],
        'checkbox' => ['checkbox', []],
    ]);

    it('creates nothing when the evidence is missing', function (string $type, array $payload) {
        $staff = submissionStaff(submissionBranch());
        $task = submissionTask('missing_'.$type, ['submission_type' => $type]);

        $result = submit($staff, $task, $payload);

        expect($result->successful)->toBeFalse()
            ->and(BriefingRecord::count())->toBe(0)
            ->and(BriefingItem::count())->toBe(0);
    })->with([
        'camera without photo' => ['camera_only', []],
        'photo and text without notes' => ['photo_and_text', ['photo_paths' => ['a.jpg']]],
        'text with blank notes' => ['text_only', ['notes' => '  ']],
    ]);

    it('denies a participant whose role lost the submit permission, even when calling the action directly', function () {
        $staff = submissionStaff(submissionBranch());
        $task = submissionTask('perm_check');
        Role::findByName('STORE_STAFF')->revokePermissionTo('submit daily briefing');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $result = submit($staff->fresh(), $task);

        expect($result->successful)->toBeFalse()
            ->and($result->reason)->toBe('not_participant')
            ->and(BriefingRecord::count())->toBe(0);
    });

    it('collects contributions from several STORE_STAFF into one bundle, each item keeping its contributor', function () {
        $branch = submissionBranch();
        $first = submissionStaff($branch);
        $second = submissionStaff($branch);
        $taskA = submissionTask('multi_a');
        $taskB = submissionTask('multi_b', ['sort_order' => 2]);

        submit($first, $taskA);
        submit($second, $taskB);

        $bundle = bundleFor($branch)->load('items');

        expect(BriefingRecord::count())->toBe(1)
            ->and($bundle->items->firstWhere('task_key', 'multi_a')->submitted_by)->toBe($first->id)
            ->and($bundle->items->firstWhere('task_key', 'multi_b')->submitted_by)->toBe($second->id);
    });

    it('stores weekly submissions from different days in the same Monday bundle', function () {
        $branch = submissionBranch();
        $task = submissionTask('weekly_a', ['period' => 'weekly']);
        $taskB = submissionTask('weekly_b', ['period' => 'weekly', 'sort_order' => 2]);

        $this->travelTo(CarbonImmutable::parse('2026-10-13 09:00:00'));
        submit(submissionStaff($branch), $task);

        $this->travelTo(CarbonImmutable::parse('2026-10-16 09:00:00'));
        submit(submissionStaff($branch), $taskB);

        $bundle = bundleFor($branch, BriefingPeriod::Weekly);

        expect(BriefingRecord::count())->toBe(1)
            ->and($bundle->period_start->toDateString())->toBe('2026-10-12')
            ->and($bundle->items()->count())->toBe(2)
            ->and($bundle->status)->toBe(BriefingBundleStatus::SupervisorReview);
    });

    it('keeps a partial bundle in draft and hands a complete one to the supervisor once', function () {
        $branch = submissionBranch();
        $staff = submissionStaff($branch);
        $taskA = submissionTask('flow_a');
        $taskB = submissionTask('flow_b', ['sort_order' => 2]);

        submit($staff, $taskA);
        expect(bundleFor($branch)->status)->toBe(BriefingBundleStatus::Draft)
            ->and(bundleFor($branch)->completed_at)->toBeNull();

        submit($staff, $taskB);
        $bundle = bundleFor($branch)->load('transitions');

        expect($bundle->status)->toBe(BriefingBundleStatus::SupervisorReview)
            ->and($bundle->completed_at)->not->toBeNull()
            ->and($bundle->transitions->pluck('to_status')->all())->toBe([BriefingBundleStatus::Draft, BriefingBundleStatus::SupervisorReview]);
    });

    it('refuses a repeated submission once the bundle is waiting for review, changing nothing', function () {
        $branch = submissionBranch();
        $staff = submissionStaff($branch);
        $task = submissionTask('double_submit');

        submit($staff, $task);
        $before = bundleFor($branch)->items()->sole()->updated_at;

        $this->travel(1)->minutes();
        $result = submit($staff, $task);

        expect($result->successful)->toBeFalse()
            ->and($result->reason)->toBe('bundle_in_review')
            ->and(bundleFor($branch)->items()->sole()->updated_at->equalTo($before))->toBeTrue()
            ->and(bundleFor($branch)->transitions()->count())->toBe(2);
    });

    it('locks approved and rejected bundles against staff edits', function (BriefingBundleStatus $status, string $reason) {
        $branch = submissionBranch();
        $staff = submissionStaff($branch);
        $task = submissionTask('locked_'.$status->value);
        $taskB = submissionTask('locked_other_'.$status->value, ['sort_order' => 2]);
        submit($staff, $task);
        bundleFor($branch)->update(['status' => $status->value]);

        $result = submit($staff, $taskB);

        expect($result->successful)->toBeFalse()
            ->and($result->reason)->toBe($reason);
    })->with([
        'approved' => [BriefingBundleStatus::Approved, 'bundle_approved'],
        'rejected' => [BriefingBundleStatus::Rejected, 'bundle_rejected'],
    ]);

    it('lets a reopened bundle fix only the flagged point, even after the deadline, then returns it to review', function () {
        $branch = submissionBranch();
        $staff = submissionStaff($branch);
        $flagged = submissionTask('reopen_flagged', ['deadline_enabled' => true, 'deadline_time' => '09:00:00']);
        $untouched = submissionTask('reopen_untouched', ['sort_order' => 2]);

        $this->travelTo(CarbonImmutable::parse('2026-10-14 08:00:00'));
        submit($staff, $flagged);
        submit($staff, $untouched);
        $bundle = bundleFor($branch);
        $bundle->update(['status' => BriefingBundleStatus::Reopened->value]);
        $bundle->items()->where('task_key', 'reopen_flagged')->update(['requires_correction' => true, 'correction_note' => 'Foto buram.']);

        $this->travelTo(CarbonImmutable::parse('2026-10-14 15:00:00'));

        expect(submit($staff, $untouched)->reason)->toBe('not_flagged_for_correction');

        $result = submit($staff, $flagged, ['photo_paths' => ['briefing-photos/fixed.jpg']]);

        expect($result->successful)->toBeTrue()
            ->and($result->item->refresh()->requires_correction)->toBeFalse()
            ->and(bundleFor($branch)->status)->toBe(BriefingBundleStatus::SupervisorReview);
    });

    it('refuses a point that was added to the task master after the bundle snapshot was taken', function () {
        $branch = submissionBranch();
        $staff = submissionStaff($branch);
        submit($staff, submissionTask('snapshot_first'));
        $added = submissionTask('snapshot_added_later', ['sort_order' => 2]);

        $result = submit($staff, $added);

        expect($result->successful)->toBeFalse()
            ->and($result->reason)->toBe('task_not_assigned');
    });

    it('still consumes a legacy per-item reopen grant past the deadline', function () {
        $branch = submissionBranch();
        $staff = submissionStaff($branch);
        $task = submissionTask('legacy_grant', ['deadline_enabled' => true, 'deadline_time' => '09:00:00']);
        $other = submissionTask('legacy_grant_other', ['sort_order' => 2]);

        $this->travelTo(CarbonImmutable::parse('2026-10-14 08:00:00'));
        submit($staff, $other);
        bundleFor($branch)->items()->create([
            'task_key' => $task->key,
            'photo_paths' => ['old.jpg'],
            'is_completed' => true,
            'completed_at' => now(),
            'review_status' => BriefingReviewStatus::Rejected->value,
            'rejection_reason' => 'Buram',
            'reviewed_by' => $staff->id,
            'reopened_at' => now(),
        ]);

        $this->travelTo(CarbonImmutable::parse('2026-10-14 15:00:00'));
        $result = submit($staff, $task, ['photo_paths' => ['new.jpg']]);

        expect($result->successful)->toBeTrue()
            ->and($result->item->refresh()->reopened_at)->toBeNull();
    });
});

describe('Employee App workspace', function () {
    it('shows progress and the waiting-for-supervisor state once the bundle is complete', function () {
        $branch = submissionBranch();
        $staff = submissionStaff($branch);
        $task = submissionTask('page_only_point');
        actingAs($staff);

        Livewire::test(DailyBriefingPage::class)
            ->call('openTaskModal', $task->key)
            ->set('cameraPhotoPaths', ['briefing-photos/page.jpg'])
            ->call('saveTask')
            ->assertSee('1/1 tugas selesai')
            ->assertSee('Menunggu persetujuan supervisor', false)
            ->assertSee('oleh '.$staff->name);
    });

    it('keeps weekly progress visible on later days of the same week', function () {
        $branch = submissionBranch();
        $staff = submissionStaff($branch);
        $task = submissionTask('page_weekly_a', ['period' => 'weekly']);
        submissionTask('page_weekly_b', ['period' => 'weekly', 'sort_order' => 2]);

        $this->travelTo(CarbonImmutable::parse('2026-10-12 09:00:00'));
        actingAs($staff);
        Livewire::test(DailyBriefingPage::class)
            ->call('openTaskModal', $task->key)
            ->set('cameraPhotoPaths', ['briefing-photos/weekly.jpg'])
            ->call('saveTask');

        $this->travelTo(CarbonImmutable::parse('2026-10-15 09:00:00'));

        expect(Livewire::test(DailyBriefingPage::class)->instance()->briefingData['weekly']['completed'])->toBe(1);
    });

    it('does not offer an approved bundle\'s points for editing', function () {
        $branch = submissionBranch();
        $staff = submissionStaff($branch);
        $task = submissionTask('page_approved');
        submissionTask('page_approved_other', ['sort_order' => 2]);
        submit($staff, $task);
        bundleFor($branch)->update(['status' => BriefingBundleStatus::Approved->value]);
        actingAs($staff);

        Livewire::test(DailyBriefingPage::class)
            ->assertDontSeeHtml("openTaskModal('page_approved')")
            ->assertDontSeeHtml("openTaskModal('page_approved_other')")
            ->call('openTaskModal', 'page_approved_other')
            ->assertSet('activeTaskKey', null);
    });

    it('refuses to submit and asks for reconciliation when several legacy rows compete for the key', function () {
        $branch = submissionBranch();
        $staff = submissionStaff($branch);
        $task = submissionTask('page_conflict');
        BriefingRecord::factory()->forBranch($branch)->onDate('2026-10-14')->count(2)->create();
        actingAs($staff);

        Livewire::test(DailyBriefingPage::class)
            ->call('openTaskModal', $task->key)
            ->set('cameraPhotoPaths', ['briefing-photos/conflict.jpg'])
            ->call('saveTask')
            ->assertNotified('Tidak Dapat Disimpan');

        expect(BriefingRecord::whereNotNull('status')->count())->toBe(0)
            ->and(BriefingItem::count())->toBe(0);
    });
});
