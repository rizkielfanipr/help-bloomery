<?php

use App\Actions\Briefing\AutoRejectBriefingBundleAction;
use App\Enums\BriefingBundleStatus;
use App\Enums\BriefingReviewStatus;
use App\Enums\BriefingTransitionSource;
use App\Filament\Helpdesk\Pages\BriefingSettingsPage;
use App\Jobs\Briefing\RecomputeBriefingBranchScoreJob;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use App\Models\BriefingRecordRequirement;
use App\Models\BriefingSettings;
use App\Models\BriefingTask;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;

/**
 * docs/daily-briefing-bundle-approval-prd.md Phase 8: the review SLA rejects whole complete bundles a
 * supervisor never reviewed, never drafts and never individual bundle items; a missed deadline with
 * nothing submitted still creates no row.
 */
function slaBundle(BriefingBundleStatus $status, string $completedAt): BriefingRecord
{
    $bundle = BriefingRecord::factory()->onDate('2026-10-05')->create([
        'period_start' => '2026-10-05',
        'period_end' => '2026-10-05',
        'status' => $status->value,
        'completed_at' => $status === BriefingBundleStatus::Draft ? null : $completedAt,
    ]);
    BriefingRecordRequirement::factory()->for($bundle, 'record')->create(['task_key' => 'sla_point']);
    BriefingItem::factory()->for($bundle, 'record')->forTask('sla_point')->create(['completed_at' => $completedAt]);

    return $bundle;
}

beforeEach(function () {
    Queue::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-14 10:00:00'));
    BriefingSettings::instance()->update(['auto_reject_after_days' => 3]);
});

it('rejects a complete bundle left in review past the SLA as a system decision with an audit row', function () {
    $bundle = slaBundle(BriefingBundleStatus::SupervisorReview, '2026-10-10 09:00:00');

    artisan('briefing:auto-reject')->assertSuccessful();

    $bundle->refresh()->load(['items', 'transitions']);
    $transition = $bundle->transitions->sole();

    expect($bundle->status)->toBe(BriefingBundleStatus::Rejected)
        ->and($bundle->reviewed_by)->toBeNull()
        ->and($bundle->review_notes)->not->toBeEmpty()
        ->and($bundle->items->sole()->review_status)->toBe(BriefingReviewStatus::Rejected)
        ->and($bundle->items->sole()->isSystemRejected())->toBeTrue()
        ->and($transition->source)->toBe(BriefingTransitionSource::SystemReconciliation)
        ->and($transition->actor_id)->toBeNull()
        ->and($transition->metadata)->toBe(['rule' => 'review_sla', 'sla_days' => 3]);

    Queue::assertPushed(RecomputeBriefingBranchScoreJob::class, 1);
});

it('leaves drafts and bundles still inside the SLA alone, including their items', function () {
    $draft = slaBundle(BriefingBundleStatus::Draft, '2026-10-01 09:00:00');
    $recent = slaBundle(BriefingBundleStatus::SupervisorReview, '2026-10-13 09:00:00');

    artisan('briefing:auto-reject')->assertSuccessful();

    expect($draft->refresh()->status)->toBe(BriefingBundleStatus::Draft)
        ->and($draft->items()->sole()->review_status)->toBe(BriefingReviewStatus::SupervisorReview)
        ->and($recent->refresh()->status)->toBe(BriefingBundleStatus::SupervisorReview);

    Queue::assertNothingPushed();
});

it('is idempotent across runs', function () {
    $bundle = slaBundle(BriefingBundleStatus::SupervisorReview, '2026-10-10 09:00:00');

    artisan('briefing:auto-reject')->assertSuccessful();
    artisan('briefing:auto-reject')->assertSuccessful();

    expect($bundle->transitions()->count())->toBe(1);
    Queue::assertPushed(RecomputeBriefingBranchScoreJob::class, 1);
});

it('lets a supervisor decision made a moment earlier win over the SLA rejection', function () {
    $bundle = slaBundle(BriefingBundleStatus::SupervisorReview, '2026-10-10 09:00:00');
    BriefingRecord::whereKey($bundle->id)->update(['status' => BriefingBundleStatus::Approved->value]);

    $applied = app(AutoRejectBriefingBundleAction::class)->execute($bundle, 'Lewat SLA', 3);

    expect($applied)->toBeFalse()
        ->and($bundle->refresh()->status)->toBe(BriefingBundleStatus::Approved)
        ->and($bundle->transitions()->count())->toBe(0);
});

it('still rejects real legacy submissions per item until they are grouped', function () {
    $legacyItem = BriefingItem::factory()->create(['completed_at' => '2026-10-01 09:00:00']);

    artisan('briefing:auto-reject')->assertSuccessful();

    expect($legacyItem->refresh()->review_status)->toBe(BriefingReviewStatus::Rejected)
        ->and($legacyItem->record->isLegacyUngrouped())->toBeTrue();
});

it('creates no row for a deadline that passed with nothing submitted', function () {
    BriefingTask::create(['key' => 'sla_deadline', 'label' => 'Deadline', 'period' => 'daily', 'submission_type' => 'camera_only', 'is_active' => true, 'include_in_score' => true, 'deadline_enabled' => true, 'deadline_time' => '09:00:00', 'sort_order' => 1]);

    artisan('briefing:auto-reject')->assertSuccessful();

    expect(BriefingRecord::count())->toBe(0)
        ->and(BriefingItem::count())->toBe(0);
});

it('previews exactly what the next run will reject on the settings page', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    slaBundle(BriefingBundleStatus::SupervisorReview, '2026-10-10 09:00:00');
    slaBundle(BriefingBundleStatus::Draft, '2026-10-01 09:00:00');
    BriefingItem::factory()->create(['completed_at' => '2026-10-01 09:00:00']);

    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $hrd = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $hrd->assignRole('HRD_STAFF');
    actingAs($hrd);

    Livewire::test(BriefingSettingsPage::class)->assertSee('1 bundle dan 1 item lama memenuhi syarat');
});
