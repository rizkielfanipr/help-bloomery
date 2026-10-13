<?php

use App\Actions\Briefing\BriefingReviewAction;
use App\Actions\Briefing\BriefingSubmissionAction;
use App\Actions\Briefing\BriefingSubmissionResult;
use App\Enums\BriefingPeriod;
use App\Enums\BriefingReviewStatus;
use App\Models\Branch;
use App\Models\BriefingBranchSetting;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use App\Models\BriefingSettings;
use App\Models\BriefingTask;
use App\Models\User;
use App\Services\BriefingAssignmentService;
use App\Services\BriefingScoreCalculator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * docs/daily-briefing-bundle-approval-prd.md Phase 1 safety net: locks the authorization, branch
 * snapshot and legacy-read behavior that the bundle refactor must preserve. Behavior that the
 * refactor is meant to change (per-item approval, raw weekly record dates) is deliberately not
 * locked in here.
 */
function characterizationParticipantBranch(): Branch
{
    $branch = Branch::factory()->create();
    BriefingBranchSetting::factory()->create(['branch_id' => $branch->id]);

    return $branch;
}

function characterizationStoreStaff(Branch $branch, array $roles = ['STORE_STAFF'], bool $isActive = true): User
{
    $user = User::factory()->create(['is_active' => $isActive, 'branch_id' => $branch->id]);
    $user->assignRole($roles);

    return $user;
}

function characterizationDailyTask(): BriefingTask
{
    return BriefingTask::create([
        'key' => 'bundle_characterization_daily',
        'label' => 'Bundle Characterization Daily',
        'period' => 'daily',
        'submission_type' => 'camera_only',
        'is_active' => true,
        'include_in_score' => true,
        'deadline_enabled' => false,
        'sort_order' => 1,
    ]);
}

function characterizationSubmit(User $actor, BriefingTask $task): BriefingSubmissionResult
{
    return app(BriefingSubmissionAction::class)->execute(
        $actor,
        $task,
        ['photo_paths' => ['briefing-photos/characterization.jpg']],
        now(),
    );
}

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Cache::flush();
});

describe('submission authorization at the action level', function () {
    it('lets an active STORE_STAFF on a participant branch submit and snapshots the branch on the record', function () {
        $branch = characterizationParticipantBranch();
        $staff = characterizationStoreStaff($branch);

        $result = characterizationSubmit($staff, characterizationDailyTask());

        expect($result->successful)->toBeTrue()
            ->and(BriefingRecord::sole()->branch_id)->toBe($branch->id);
    });

    it('denies an inactive STORE_STAFF even on a participant branch', function () {
        $staff = characterizationStoreStaff(characterizationParticipantBranch(), isActive: false);

        $result = characterizationSubmit($staff, characterizationDailyTask());

        expect($result->successful)->toBeFalse()
            ->and($result->reason)->toBe('not_participant')
            ->and(BriefingRecord::count())->toBe(0);
    });

    it('does not treat a SUPERVISOR_STORE as a participant just because they have a participant branch', function () {
        $supervisor = characterizationStoreStaff(characterizationParticipantBranch(), ['SUPERVISOR_STORE']);

        $result = characterizationSubmit($supervisor, characterizationDailyTask());

        expect($result->successful)->toBeFalse()
            ->and($result->reason)->toBe('not_participant')
            ->and(BriefingRecord::count())->toBe(0);
    });

    it('lets a user who is both SUPERVISOR_STORE and STORE_STAFF submit, since they satisfy the participant rule', function () {
        $user = characterizationStoreStaff(characterizationParticipantBranch(), ['SUPERVISOR_STORE', 'STORE_STAFF']);

        $result = characterizationSubmit($user, characterizationDailyTask());

        expect($result->successful)->toBeTrue();
    });

    it('denies a STORE_STAFF whose branch participation has already ended', function () {
        $branch = Branch::factory()->create();
        BriefingBranchSetting::factory()
            ->startingOn(now()->subMonth()->toDateString())
            ->endedOn(now()->subDay()->toDateString())
            ->create(['branch_id' => $branch->id]);
        $staff = characterizationStoreStaff($branch);

        $result = characterizationSubmit($staff, characterizationDailyTask());

        expect($result->successful)->toBeFalse()
            ->and($result->reason)->toBe('not_participant');
    });

    it('assigns no tasks to a STORE_STAFF whose branch is not a participant, even when global tasks exist', function () {
        characterizationDailyTask();
        $staff = characterizationStoreStaff(Branch::factory()->create());

        $tasks = app(BriefingAssignmentService::class)->tasksFor($staff, BriefingPeriod::Daily, now());

        expect($tasks)->toBeEmpty();
    });
});

describe('review scope bypass', function () {
    it('lets a SUPERADMIN approve an item in a branch they were never explicitly given access to', function () {
        $item = BriefingItem::factory()->create();

        $superAdmin = User::factory()->create(['is_active' => true]);
        $superAdmin->assignRole('SUPERADMIN');

        $result = app(BriefingReviewAction::class)->approve($superAdmin, $item);

        expect($result->successful)->toBeTrue()
            ->and($item->refresh()->review_status)->toBe(BriefingReviewStatus::Approved);
    });
});

describe('history uses the record branch snapshot', function () {
    it('keeps crediting an approved occurrence to the record branch after the submitter moves branch', function () {
        BriefingSettings::instance()->update(['scoring_started_at' => '2020-01-01', 'passing_score' => 80]);

        $originalBranch = characterizationParticipantBranch();
        $newBranch = characterizationParticipantBranch();
        $task = characterizationDailyTask();
        $staff = characterizationStoreStaff($originalBranch);
        $reviewer = User::factory()->create();

        $record = BriefingRecord::factory()
            ->submittedBy($staff)
            ->onDate('2026-06-15')
            ->create();
        BriefingItem::factory()->for($record, 'record')->forTask($task->key)->approvedBy($reviewer)->create();

        $staff->update(['branch_id' => $newBranch->id]);

        $calculator = app(BriefingScoreCalculator::class);

        expect($calculator->calculate($originalBranch, 2026, 6)->periods['daily']['approved'])->toBe(1)
            ->and($calculator->calculate($newBranch, 2026, 6)->periods['daily']['approved'])->toBe(0);
    });
});

describe('legacy data stays readable', function () {
    it('reads a pre-rename pending item as awaiting supervisor review and still lets a supervisor approve it', function () {
        $item = BriefingItem::factory()->legacyPending()->create();
        $branchId = $item->record->branch_id;

        expect($item->review_status)->toBe(BriefingReviewStatus::LegacyPending)
            ->and($item->review_status->isAwaitingSupervisorReview())->toBeTrue();

        $supervisor = User::factory()->create(['is_active' => true]);
        $supervisor->assignRole('SUPERVISOR_STORE');
        $supervisor->syncBranchAccess([$branchId], $branchId);

        expect(app(BriefingReviewAction::class)->approve($supervisor, $item)->successful)->toBeTrue();
    });

    it('distinguishes a system rejection with no submission from a human rejection of a real submission', function () {
        $reviewer = User::factory()->create();

        $synthetic = BriefingItem::factory()->systemRejectedWithoutSubmission()->create();
        $human = BriefingItem::factory()->forTask('daily_selfie_sore')->rejectedBy($reviewer)->create();

        expect($synthetic->isSystemRejected())->toBeTrue()
            ->and($human->isSystemRejected())->toBeFalse();
    });
});

describe('route access by permission', function () {
    beforeEach(function () {
        Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    });

    it('opens the Daily Briefing back-office pages for HRD_STAFF', function (string $routeName) {
        $hrd = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
        $hrd->assignRole('HRD_STAFF');
        actingAs($hrd);

        get(route($routeName))->assertSuccessful();
    })->with([
        'Monitoring Poin' => 'filament.helpdesk.resources.briefing-items.index',
        'Kelola Poin' => 'filament.helpdesk.resources.briefing-tasks.index',
        'Nilai Briefing' => 'filament.helpdesk.pages.briefing-score-workspace-page',
        'Pengaturan' => 'filament.helpdesk.pages.briefing-settings-page',
    ]);

    it('forbids the Daily Briefing back-office pages to a back-office user without Briefing permissions', function (string $routeName) {
        $user = User::factory()->create(['is_active' => true]);
        $user->givePermissionTo('access backoffice');
        actingAs($user);

        get(route($routeName))->assertForbidden();
    })->with([
        'Monitoring Poin' => 'filament.helpdesk.resources.briefing-items.index',
        'Kelola Poin' => 'filament.helpdesk.resources.briefing-tasks.index',
        'Nilai Briefing' => 'filament.helpdesk.pages.briefing-score-workspace-page',
        'Pengaturan' => 'filament.helpdesk.pages.briefing-settings-page',
    ]);
});
