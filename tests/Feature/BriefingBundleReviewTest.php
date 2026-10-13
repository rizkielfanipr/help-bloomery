<?php

use App\Actions\Briefing\BriefingSubmissionAction;
use App\Actions\Briefing\ReopenBriefingBundleAction;
use App\Actions\Briefing\ReviewBriefingBundleAction;
use App\Enums\BriefingBundleStatus;
use App\Enums\BriefingPeriod;
use App\Enums\BriefingReviewStatus;
use App\Filament\Helpdesk\Resources\BriefingBundles\BriefingBundleResource;
use App\Filament\Helpdesk\Resources\BriefingBundles\Pages\ListBriefingBundles;
use App\Jobs\Briefing\RecomputeBriefingBranchScoreJob;
use App\Models\Branch;
use App\Models\BriefingBranchSetting;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use App\Models\BriefingRecordRequirement;
use App\Models\BriefingTask;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * docs/daily-briefing-bundle-approval-prd.md Phase 5: one approve/reject per bundle, audited reopen,
 * correction flags, branch-scoped Monitoring with one row per bundle, and a targeted recompute
 * queued only after commit.
 */
function reviewBranch(): Branch
{
    $branch = Branch::factory()->create();
    BriefingBranchSetting::factory()->create(['branch_id' => $branch->id]);

    return $branch;
}

/**
 * A complete bundle awaiting review, built directly from factories.
 *
 * @param  list<string>  $taskKeys
 */
function reviewableBundle(Branch $branch, array $taskKeys = ['review_a', 'review_b'], string $periodStart = '2026-10-14', BriefingPeriod $period = BriefingPeriod::Daily, ?string $periodEnd = null): BriefingRecord
{
    $bundle = BriefingRecord::factory()->forBranch($branch)->forPeriod($period, $periodStart)->create([
        'period_start' => $periodStart,
        'period_end' => $periodEnd ?? $periodStart,
        'status' => BriefingBundleStatus::SupervisorReview->value,
        'completed_at' => now(),
    ]);

    foreach ($taskKeys as $index => $key) {
        BriefingRecordRequirement::factory()->for($bundle, 'record')->create(['task_key' => $key, 'label' => 'Poin '.$key, 'sort_order' => $index]);
        BriefingItem::factory()->for($bundle, 'record')->forTask($key)->create();
    }

    return $bundle;
}

function supervisorOf(Branch $branch): User
{
    $supervisor = User::factory()->create(['is_active' => true]);
    $supervisor->assignRole('SUPERVISOR_STORE');
    $supervisor->syncBranchAccess([$branch->id], $branch->id);

    return $supervisor;
}

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Cache::flush();
    Queue::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-14 10:00:00'));
});

describe('approve', function () {
    it('approves the whole bundle once, mirrors the decision onto every item, audits it, and queues a recompute', function () {
        $branch = reviewBranch();
        $bundle = reviewableBundle($branch);
        $supervisor = supervisorOf($branch);

        $result = app(ReviewBriefingBundleAction::class)->approve($supervisor, $bundle, 'Rapi.');

        $bundle->refresh()->load(['items', 'transitions']);

        expect($result->successful)->toBeTrue()
            ->and($result->changed)->toBeTrue()
            ->and($bundle->status)->toBe(BriefingBundleStatus::Approved)
            ->and($bundle->reviewed_by)->toBe($supervisor->id)
            ->and($bundle->review_notes)->toBe('Rapi.')
            ->and($bundle->items->every(fn (BriefingItem $item): bool => $item->review_status === BriefingReviewStatus::Approved && $item->reviewed_by === $supervisor->id))->toBeTrue()
            ->and($bundle->transitions->sole()->to_status)->toBe(BriefingBundleStatus::Approved);

        Queue::assertPushed(RecomputeBriefingBranchScoreJob::class, fn (RecomputeBriefingBranchScoreJob $job): bool => $job->branchId === $branch->id && $job->year === 2026 && $job->month === 10);
    });

    it('treats a repeated approval as an idempotent no-op with no second audit row or recompute', function () {
        $branch = reviewBranch();
        $bundle = reviewableBundle($branch);
        $supervisor = supervisorOf($branch);
        $action = app(ReviewBriefingBundleAction::class);

        $action->approve($supervisor, $bundle);
        $repeat = $action->approve($supervisor, $bundle);

        expect($repeat->successful)->toBeTrue()
            ->and($repeat->changed)->toBeFalse()
            ->and($bundle->transitions()->count())->toBe(1);

        Queue::assertPushed(RecomputeBriefingBranchScoreJob::class, 1);
    });

    it('refuses a draft bundle and a bundle that is no longer complete', function (string $case) {
        $branch = reviewBranch();
        $bundle = reviewableBundle($branch);

        match ($case) {
            'draft' => $bundle->update(['status' => BriefingBundleStatus::Draft->value]),
            'incomplete' => $bundle->items()->first()->update(['photo_paths' => []]),
        };

        $result = app(ReviewBriefingBundleAction::class)->approve(supervisorOf($branch), $bundle);

        expect($result->successful)->toBeFalse()
            ->and($result->reason)->toBe($case === 'draft' ? 'not_awaiting_review' : 'incomplete')
            ->and($bundle->refresh()->status)->not->toBe(BriefingBundleStatus::Approved);

        Queue::assertNothingPushed();
    })->with(['draft', 'incomplete']);

    it('refuses a decision taken from a modal opened before the bundle last changed', function () {
        $branch = reviewBranch();
        $bundle = reviewableBundle($branch);
        $seenAt = $bundle->updated_at->copy();

        $this->travel(5)->minutes();
        $bundle->touch();

        $result = app(ReviewBriefingBundleAction::class)->approve(supervisorOf($branch), $bundle, null, $seenAt);

        expect($result->reason)->toBe('stale')
            ->and($bundle->refresh()->status)->toBe(BriefingBundleStatus::SupervisorReview);
    });

    it('refuses reviewers outside the bundle branch and contributors without review rights', function (string $who) {
        $branch = reviewBranch();
        $bundle = reviewableBundle($branch);

        $actor = match ($who) {
            'supervisor of another branch' => supervisorOf(reviewBranch()),
            'store staff of the branch' => tap(User::factory()->create(['is_active' => true, 'branch_id' => $branch->id]))->assignRole('STORE_STAFF'),
        };

        $result = app(ReviewBriefingBundleAction::class)->approve($actor, $bundle);

        expect($result->successful)->toBeFalse()
            ->and($result->reason)->toBe('unauthorized')
            ->and($bundle->refresh()->status)->toBe(BriefingBundleStatus::SupervisorReview);
    })->with(['supervisor of another branch', 'store staff of the branch']);

    it('queues a recompute for both months of a weekly bundle that straddles them', function () {
        $branch = reviewBranch();
        $bundle = reviewableBundle($branch, ['weekly_a'], '2026-09-28', BriefingPeriod::Weekly, '2026-10-04');

        app(ReviewBriefingBundleAction::class)->approve(supervisorOf($branch), $bundle);

        Queue::assertPushed(RecomputeBriefingBranchScoreJob::class, 2);
        Queue::assertPushed(RecomputeBriefingBranchScoreJob::class, fn ($job): bool => $job->month === 9);
        Queue::assertPushed(RecomputeBriefingBranchScoreJob::class, fn ($job): bool => $job->month === 10);
    });
});

describe('reject and reopen', function () {
    it('rejects the bundle and flags only the chosen points with their own notes', function () {
        $branch = reviewBranch();
        $bundle = reviewableBundle($branch);

        $result = app(ReviewBriefingBundleAction::class)->reject(supervisorOf($branch), $bundle, 'Foto kurang jelas.', [
            'review_a' => 'Ulangi foto dari depan.',
        ]);

        $bundle->refresh()->load('items');
        $flagged = $bundle->items->firstWhere('task_key', 'review_a');
        $untouched = $bundle->items->firstWhere('task_key', 'review_b');

        expect($result->successful)->toBeTrue()
            ->and($bundle->status)->toBe(BriefingBundleStatus::Rejected)
            ->and($bundle->review_notes)->toBe('Foto kurang jelas.')
            ->and($flagged->requires_correction)->toBeTrue()
            ->and($flagged->correction_note)->toBe('Ulangi foto dari depan.')
            ->and($untouched->requires_correction)->toBeFalse()
            ->and($bundle->transitions()->sole()->metadata)->toBe(['correction_task_keys' => ['review_a']]);
    });

    it('requires a reason and refuses to flag a point outside the bundle', function () {
        $branch = reviewBranch();
        $bundle = reviewableBundle($branch);
        $action = app(ReviewBriefingBundleAction::class);
        $supervisor = supervisorOf($branch);

        expect($action->reject($supervisor, $bundle, '  ')->reason)->toBe('reason_required')
            ->and($action->reject($supervisor, $bundle, 'Alasan', ['not_in_bundle' => null])->reason)->toBe('unknown_item')
            ->and($bundle->refresh()->status)->toBe(BriefingBundleStatus::SupervisorReview);
    });

    it('reopens straight away for correction so staff can fix the flagged point and resubmit it for review', function () {
        $branch = reviewBranch();
        $task = BriefingTask::create(['key' => 'reopen_flow', 'label' => 'Reopen Flow', 'period' => 'daily', 'submission_type' => 'camera_only', 'is_active' => true, 'include_in_score' => true, 'sort_order' => 1]);
        $staff = tap(User::factory()->create(['is_active' => true, 'branch_id' => $branch->id]))->assignRole('STORE_STAFF');
        app(BriefingSubmissionAction::class)->execute($staff, $task, ['photo_paths' => ['first.jpg']], now());
        $bundle = BriefingRecord::sole();

        app(ReviewBriefingBundleAction::class)->reject(supervisorOf($branch), $bundle, 'Buram.', ['reopen_flow' => null], reopenForCorrection: true);

        expect($bundle->refresh()->status)->toBe(BriefingBundleStatus::Reopened)
            ->and($bundle->transitions()->pluck('to_status')->map->value->all())->toBe(['draft', 'supervisor_review', 'rejected', 'reopened']);

        $resubmit = app(BriefingSubmissionAction::class)->execute($staff, $task, ['photo_paths' => ['second.jpg']], now());

        expect($resubmit->successful)->toBeTrue()
            ->and($bundle->refresh()->status)->toBe(BriefingBundleStatus::SupervisorReview)
            ->and($bundle->items()->sole()->requires_correction)->toBeFalse();
    });

    it('reopening an approved bundle withdraws the approval from the bundle and every item', function () {
        $branch = reviewBranch();
        $bundle = reviewableBundle($branch);
        $supervisor = supervisorOf($branch);
        app(ReviewBriefingBundleAction::class)->approve($supervisor, $bundle);

        $result = app(ReopenBriefingBundleAction::class)->execute($supervisor, $bundle, 'Foto tertukar.');

        $bundle->refresh()->load(['items', 'transitions']);

        expect($result->successful)->toBeTrue()
            ->and($bundle->status)->toBe(BriefingBundleStatus::Reopened)
            ->and($bundle->reviewed_by)->toBeNull()
            ->and($bundle->items->every(fn (BriefingItem $item): bool => $item->review_status === BriefingReviewStatus::SupervisorReview))->toBeTrue()
            ->and($bundle->transitions->last()->reason)->toBe('Foto tertukar.');

        // The approval's recompute has not started yet, so the reopen collapses into it; that pending
        // job reads the reopened state when it runs.
        Queue::assertPushed(RecomputeBriefingBranchScoreJob::class, 1);
    });

    it('only reopens approved or rejected bundles, with a reason, inside the reviewer scope', function () {
        $branch = reviewBranch();
        $bundle = reviewableBundle($branch);
        $action = app(ReopenBriefingBundleAction::class);

        expect($action->execute(supervisorOf($branch), $bundle, 'Alasan')->reason)->toBe('not_reopenable')
            ->and($action->execute(supervisorOf($branch), $bundle, '')->reason)->toBe('reason_required')
            ->and($action->execute(supervisorOf(reviewBranch()), $bundle, 'Alasan')->reason)->toBe('unauthorized');
    });
});

describe('Monitoring Poin', function () {
    beforeEach(function () {
        Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    });

    it('lists one row per bundle in every status by default, and never legacy ungrouped rows', function () {
        $branch = reviewBranch();
        $waiting = reviewableBundle($branch);
        $draft = BriefingRecord::factory()->forBranch($branch)->onDate('2026-10-13')->create(['period_start' => '2026-10-13', 'status' => BriefingBundleStatus::Draft->value]);
        $legacy = BriefingRecord::factory()->forBranch($branch)->onDate('2026-10-12')->create();
        actingAs(supervisorOf($branch));

        Livewire::test(ListBriefingBundles::class)
            ->assertCanSeeTableRecords([$waiting, $draft])
            ->assertCanNotSeeTableRecords([$legacy])
            ->assertCountTableRecords(2)
            ->filterTable('status', BriefingBundleStatus::Draft->value)
            ->assertCanSeeTableRecords([$draft])
            ->assertCanNotSeeTableRecords([$waiting, $legacy]);
    });

    it('scopes the list to the supervisor\'s branches and fails closed without the view permission', function () {
        $own = reviewableBundle($ownBranch = reviewBranch());
        $other = reviewableBundle(reviewBranch());
        actingAs(supervisorOf($ownBranch));

        Livewire::test(ListBriefingBundles::class)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$other]);

        actingAs(User::factory()->create(['is_active' => true, 'access_all_branches' => true]));

        expect(BriefingBundleResource::getEloquentQuery()->count())->toBe(0);
    });

    it('approves a bundle from the review modal', function () {
        $branch = reviewBranch();
        $bundle = reviewableBundle($branch);
        actingAs(supervisorOf($branch));

        Livewire::test(ListBriefingBundles::class)
            ->callAction([TestAction::make('review')->table($bundle), TestAction::make('approve')], data: ['notes' => 'OK'])
            ->assertNotified('Bundle briefing disetujui.');

        expect($bundle->refresh()->status)->toBe(BriefingBundleStatus::Approved);
    });

    it('rejects a bundle from the review modal with a flagged point and reopens it for correction', function () {
        $branch = reviewBranch();
        $bundle = reviewableBundle($branch);
        actingAs(supervisorOf($branch));

        Livewire::test(ListBriefingBundles::class)
            ->callAction([TestAction::make('review')->table($bundle), TestAction::make('reject')], data: [
                'reason' => 'Perlu diulang.',
                'corrections' => ['review_b' => ['flag' => true, 'note' => 'Sudut kurang lengkap.']],
                'reopen_for_correction' => true,
            ])
            ->assertNotified('Bundle ditolak dan dibuka untuk perbaikan.');

        expect($bundle->refresh()->status)->toBe(BriefingBundleStatus::Reopened)
            ->and($bundle->items()->where('task_key', 'review_b')->value('correction_note'))->toBe('Sudut kurang lengkap.')
            ->and($bundle->items()->where('task_key', 'review_a')->value('requires_correction'))->toBeFalsy();
    });

    it('lets the review and reject modals scroll their content inside the viewport', function () {
        $branch = reviewBranch();
        $bundle = reviewableBundle($branch);
        actingAs(supervisorOf($branch));

        $scrolls = fn (Action $action): bool => str_contains($action->getExtraModalWindowAttributeBag()->get('class', ''), 'briefing-bundle-modal');

        $hasNoFooterButtons = fn (Action $action): bool => $action->getModalSubmitAction() === null && $action->getModalCancelAction() === null;

        Livewire::test(ListBriefingBundles::class)
            ->assertActionExists(TestAction::make('review')->table($bundle), $scrolls)
            ->assertActionExists(TestAction::make('review')->table($bundle), $hasNoFooterButtons)
            ->assertActionExists([TestAction::make('review')->table($bundle), TestAction::make('reject')], $scrolls);
    });

    it('offers no Excel export on the monitoring table', function () {
        $branch = reviewBranch();
        actingAs(supervisorOf($branch));

        Livewire::test(ListBriefingBundles::class)
            ->assertActionDoesNotExist(TestAction::make('export_bundles')->table());

        expect(Route::has('helpdesk.exports.briefing-bundles'))->toBeFalse();
    });

    it('shows the table without status summary cards above it', function () {
        $branch = reviewBranch();
        reviewableBundle($branch);
        actingAs(supervisorOf($branch));

        Livewire::test(ListBriefingBundles::class)
            ->assertDontSeeText('Seluruh bundle dalam cakupan cabang Anda')
            ->assertDontSeeText('Bundle lengkap yang siap disetujui atau ditolak');
    });

    it('renders the filters inline in the column headers like the ERP request table', function () {
        $branch = reviewBranch();
        $otherBranch = reviewBranch();
        $bundle = reviewableBundle($branch);
        $other = reviewableBundle($branch, ['s_c'], '2026-10-12');
        actingAs(supervisorOf($branch));

        Livewire::test(ListBriefingBundles::class)
            ->assertSeeHtml('wire:model.live="tableFilters.status.value"')
            ->assertSeeHtml('wire:model.live="tableFilters.branch_id.value"')
            ->assertSeeHtml('tableFilters.period_start.from')
            ->assertSeeHtml('<option value="'.$branch->id.'">')
            ->assertDontSeeHtml('<option value="'.$otherBranch->id.'">')
            ->set('tableFilters.period_start.from', '2026-10-13')
            ->assertCanSeeTableRecords([$bundle])
            ->assertCanNotSeeTableRecords([$other])
            ->assertDontSeeText('Active filters')
            ->assertSeeHtml('briefing-bundle-table');
    });

    it('shows contributors and the reviewer in the detail modal instead of the table', function () {
        $branch = reviewBranch();
        $bundle = reviewableBundle($branch);
        $bundle->items()->first()->update(['submitted_by' => User::factory()->create(['name' => 'Rina Kontributor'])->id]);
        actingAs(supervisorOf($branch));

        Livewire::test(ListBriefingBundles::class)
            ->assertTableColumnDoesNotExist('contributors')
            ->assertTableColumnDoesNotExist('reviewer.name')
            ->mountAction(TestAction::make('review')->table($bundle))
            ->assertMountedActionModalSee(['Kontributor', 'Rina Kontributor', 'Belum direview']);
    });

    it('does not credit a sender for a point that holds no submission', function () {
        $branch = reviewBranch();
        $bundle = reviewableBundle($branch, ['review_a']);
        $bundle->items()->update(['is_completed' => false, 'photo_paths' => null, 'notes' => null, 'submitted_by' => User::factory()->create()->id, 'submitted_at' => now()]);
        actingAs(supervisorOf($branch));

        Livewire::test(ListBriefingBundles::class)
            ->mountAction(TestAction::make('review')->table($bundle))
            ->assertMountedActionModalSee('Belum Diisi')
            ->assertMountedActionModalDontSee('Dikirim oleh');
    });

    it('hides approve and reject for a draft bundle', function () {
        $branch = reviewBranch();
        $draft = reviewableBundle($branch);
        $draft->update(['status' => BriefingBundleStatus::Draft->value]);
        actingAs(supervisorOf($branch));

        Livewire::test(ListBriefingBundles::class)
            ->filterTable('status', BriefingBundleStatus::Draft->value)
            ->assertActionHidden([TestAction::make('review')->table($draft), TestAction::make('approve')])
            ->assertActionHidden([TestAction::make('review')->table($draft), TestAction::make('reject')]);
    });

    it('keeps the query count flat as the number of bundles grows', function () {
        $branch = reviewBranch();
        $supervisor = supervisorOf($branch);
        actingAs($supervisor);

        $countQueries = function (int $bundles) use ($branch): int {
            BriefingRecord::query()->delete();
            foreach (range(1, $bundles) as $day) {
                reviewableBundle($branch, ['n_'.$day.'_a', 'n_'.$day.'_b'], sprintf('2026-10-%02d', $day));
            }

            DB::flushQueryLog();
            DB::enableQueryLog();
            Livewire::test(ListBriefingBundles::class);
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        // The first render also warms one-off caches (permissions, roles), so it is not measured.
        $countQueries(1);

        expect($countQueries(8))->toBe($countQueries(2));
    });

    it('opens from the sidebar and keeps the Daily Briefing group expanded on its route', function () {
        $hrd = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
        $hrd->assignRole('HRD_STAFF');
        actingAs($hrd);

        $html = get(route('filament.helpdesk.resources.briefing-bundles.index'))
            ->assertSuccessful()
            ->assertSee(route('filament.helpdesk.resources.briefing-bundles.index'), false)
            ->getContent();

        expect($html)->toMatch('/openGroups:\s*\[(\\\\u0022|")daily_briefing(\\\\u0022|")\]/');
    });
});
