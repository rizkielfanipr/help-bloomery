<?php

use App\Enums\BriefingBundleStatus;
use App\Enums\BriefingPeriod;
use App\Enums\BriefingReviewStatus;
use App\Enums\BriefingTransitionSource;
use App\Models\Branch;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use App\Models\BriefingRecordRequirement;
use App\Models\BriefingRecordTransition;
use App\Models\BriefingScore;
use App\Models\BriefingTask;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\artisan;

/**
 * docs/daily-briefing-bundle-approval-prd.md Phase 9: grouping legacy rows into bundles is a report
 * unless explicitly executed, converts only deterministic groups, keeps every piece of evidence and
 * its source ids, never duplicates on re-run, and can be restored from its snapshot.
 */
function groupingTask(string $key, BriefingPeriod $period = BriefingPeriod::Daily, string $type = 'camera_only', string $createdAt = '2026-01-01 00:00:00', ?int $branchId = null): BriefingTask
{
    $task = BriefingTask::create(['key' => $key, 'label' => 'Poin '.$key, 'period' => $period->value, 'submission_type' => $type, 'is_active' => true, 'include_in_score' => true, 'sort_order' => 1, 'branch_id' => $branchId]);
    $task->forceFill(['created_at' => $createdAt])->save();

    return $task;
}

/**
 * @param  array<string, array<string, mixed>>  $items  task key => item overrides
 */
function legacyRecord(Branch $branch, BriefingPeriod $period, string $date, array $items): BriefingRecord
{
    $record = BriefingRecord::factory()->forBranch($branch)->forPeriod($period, $date)->create();

    foreach ($items as $key => $overrides) {
        BriefingItem::factory()->for($record, 'record')->forTask($key)->create(array_merge(['completed_at' => $date.' 08:00:00'], $overrides));
    }

    return $record;
}

function approvedBy(User $reviewer, string $at = '2026-09-02 10:00:00'): array
{
    return ['review_status' => BriefingReviewStatus::Approved->value, 'reviewed_by' => $reviewer->id, 'reviewed_at' => $at];
}

beforeEach(function () {
    Cache::flush();
    Queue::fake();
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-10-14 10:00:00'));
    $this->branch = Branch::factory()->create();
    $this->reviewer = User::factory()->create();
    groupingTask('g_a');
    groupingTask('g_b');
});

it('only reports in a dry run and changes nothing', function () {
    legacyRecord($this->branch, BriefingPeriod::Daily, '2026-09-01', ['g_a' => approvedBy($this->reviewer), 'g_b' => approvedBy($this->reviewer)]);

    artisan('briefing:group-legacy-bundles')
        ->expectsOutputToContain('DRY RUN')
        ->expectsOutputToContain('approved')
        ->assertSuccessful();

    expect(BriefingRecord::whereNotNull('status')->count())->toBe(0)
        ->and(BriefingRecordRequirement::count())->toBe(0)
        ->and(BriefingRecordTransition::count())->toBe(0);
});

it('refuses to execute without a snapshot path', function () {
    artisan('briefing:group-legacy-bundles --execute')->assertFailed();
});

it('converts a consistent approved group and keeps evidence, contributor, reviewer, timestamps and source ids', function () {
    $record = legacyRecord($this->branch, BriefingPeriod::Daily, '2026-09-01', [
        'g_a' => [...approvedBy($this->reviewer, '2026-09-02 10:00:00'), 'photo_paths' => ['briefing-photos/a.jpg']],
        'g_b' => [...approvedBy($this->reviewer, '2026-09-02 11:30:00'), 'photo_paths' => ['briefing-photos/b.jpg']],
    ]);

    artisan('briefing:group-legacy-bundles --execute --snapshot=grouping/run1.jsonl')->assertSuccessful();

    $bundle = $record->refresh()->load(['items', 'requirements', 'transitions']);
    $item = $bundle->items->firstWhere('task_key', 'g_a');

    expect($bundle->status)->toBe(BriefingBundleStatus::Approved)
        ->and($bundle->period_start->toDateString())->toBe('2026-09-01')
        ->and($bundle->reviewed_by)->toBe($this->reviewer->id)
        ->and($bundle->reviewed_at->toDateTimeString())->toBe('2026-09-02 11:30:00')
        ->and($bundle->requirements->pluck('task_key')->sort()->values()->all())->toBe(['g_a', 'g_b'])
        ->and($item->photo_paths)->toBe(['briefing-photos/a.jpg'])
        ->and($item->submitted_by)->toBe($record->user_id)
        ->and($item->submitted_at->toDateTimeString())->toBe('2026-09-01 08:00:00')
        ->and($item->reviewed_by)->toBe($this->reviewer->id)
        ->and($bundle->transitions->sole()->source)->toBe(BriefingTransitionSource::Migration)
        ->and($bundle->transitions->sole()->metadata['source_record_ids'])->toBe([$record->id])
        ->and(Storage::disk('local')->get('grouping/run1.jsonl'))->not->toBeEmpty()
        ->and(BriefingScore::count())->toBe(0);

    Queue::assertNothingPushed();
});

it('does not duplicate anything when executed again', function () {
    legacyRecord($this->branch, BriefingPeriod::Daily, '2026-09-01', ['g_a' => approvedBy($this->reviewer), 'g_b' => approvedBy($this->reviewer)]);

    artisan('briefing:group-legacy-bundles --execute --snapshot=grouping/run1.jsonl')->assertSuccessful();
    artisan('briefing:group-legacy-bundles --execute --snapshot=grouping/run2.jsonl')->assertSuccessful();

    expect(BriefingRecord::whereNotNull('status')->count())->toBe(1)
        ->and(BriefingRecordTransition::count())->toBe(1)
        ->and(BriefingRecordRequirement::count())->toBe(2)
        ->and(Storage::disk('local')->exists('grouping/run2.jsonl'))->toBeFalse();
});

it('merges a weekly obligation split across days into one Monday bundle, recording the moved items', function () {
    groupingTask('g_w1', BriefingPeriod::Weekly);
    groupingTask('g_w2', BriefingPeriod::Weekly);
    $tuesday = legacyRecord($this->branch, BriefingPeriod::Weekly, '2026-09-08', ['g_w1' => []]);
    $thursday = legacyRecord($this->branch, BriefingPeriod::Weekly, '2026-09-10', ['g_w2' => []]);
    $movedItemId = $thursday->items()->sole()->id;

    artisan('briefing:group-legacy-bundles --execute --snapshot=grouping/weekly.jsonl')->assertSuccessful();

    $bundle = $tuesday->refresh()->load(['items', 'transitions']);

    expect($bundle->status)->toBe(BriefingBundleStatus::SupervisorReview)
        ->and($bundle->period_start->toDateString())->toBe('2026-09-07')
        ->and($bundle->period_end->toDateString())->toBe('2026-09-13')
        ->and($bundle->items->pluck('task_key')->sort()->values()->all())->toBe(['g_w1', 'g_w2'])
        ->and($bundle->transitions->sole()->metadata['moved_item_ids'])->toBe([$movedItemId])
        ->and($thursday->refresh()->isLegacyUngrouped())->toBeTrue()
        ->and($thursday->items()->count())->toBe(0);
});

it('normalizes a monthly row to the first of the month', function () {
    groupingTask('g_m', BriefingPeriod::Monthly);
    $record = legacyRecord($this->branch, BriefingPeriod::Monthly, '2026-09-15', ['g_m' => []]);

    artisan('briefing:group-legacy-bundles --execute --snapshot=grouping/monthly.jsonl')->assertSuccessful();

    expect($record->refresh()->period_start->toDateString())->toBe('2026-09-01');
});

it('turns a partial real submission into a draft', function () {
    $record = legacyRecord($this->branch, BriefingPeriod::Daily, '2026-09-01', ['g_a' => []]);

    artisan('briefing:group-legacy-bundles --execute --snapshot=grouping/draft.jsonl')->assertSuccessful();

    expect($record->refresh()->status)->toBe(BriefingBundleStatus::Draft)
        ->and($record->completed_at)->toBeNull();
});

it('leaves ambiguous groups untouched for manual review', function (Closure $setup, string $reason) {
    $records = $setup($this);

    artisan('briefing:group-legacy-bundles --execute --snapshot=grouping/manual.jsonl')
        ->expectsOutputToContain($reason)
        ->assertSuccessful();

    foreach ($records as $record) {
        expect($record->refresh()->isLegacyUngrouped())->toBeTrue();
    }

    expect(BriefingRecordTransition::count())->toBe(0);
})->with([
    'same point submitted twice' => [fn ($test) => [
        legacyRecord($test->branch, BriefingPeriod::Daily, '2026-09-01', ['g_a' => [], 'g_b' => []]),
        legacyRecord($test->branch, BriefingPeriod::Daily, '2026-09-01', ['g_a' => []]),
    ], 'duplicate_task'],
    'mixed decisions' => [fn ($test) => [
        legacyRecord($test->branch, BriefingPeriod::Daily, '2026-09-01', ['g_a' => approvedBy($test->reviewer), 'g_b' => ['review_status' => 'rejected', 'reviewed_by' => $test->reviewer->id, 'reviewed_at' => '2026-09-02 10:00:00', 'rejection_reason' => 'Buram']]),
    ], 'mixed_status'],
    'two different reviewers' => [fn ($test) => [
        legacyRecord($test->branch, BriefingPeriod::Daily, '2026-09-01', ['g_a' => approvedBy($test->reviewer), 'g_b' => approvedBy(User::factory()->create())]),
    ], 'inconsistent_reviewer'],
    'decisions a week apart' => [fn ($test) => [
        legacyRecord($test->branch, BriefingPeriod::Daily, '2026-09-01', ['g_a' => approvedBy($test->reviewer, '2026-09-02 10:00:00'), 'g_b' => approvedBy($test->reviewer, '2026-09-09 10:00:00')]),
    ], 'inconsistent_review_time'],
    'a point no longer assigned' => [fn ($test) => [
        legacyRecord($test->branch, BriefingPeriod::Daily, '2026-09-01', ['g_a' => [], 'g_b' => [], 'g_retired' => []]),
    ], 'unassigned_task'],
    'a real submission rejected by the SLA' => [fn ($test) => [
        legacyRecord($test->branch, BriefingPeriod::Daily, '2026-09-01', ['g_a' => [], 'g_b' => ['review_status' => 'rejected', 'reviewed_by' => null, 'reviewed_at' => '2026-09-05 10:00:00']]),
    ], 'system_rejected_real_submission'],
    'a row with no branch' => [fn ($test) => [
        BriefingRecord::factory()->create(['branch_id' => null, 'user_id' => User::factory()->create(['branch_id' => null])->id, 'record_date' => '2026-09-01']),
    ], 'missing_branch_or_date'],
]);

it('leaves an empty duplicate slot in its own row and bundles the row without one', function () {
    $withSlot = legacyRecord($this->branch, BriefingPeriod::Daily, '2026-09-01', []);
    $slot = BriefingItem::factory()->for($withSlot, 'record')->forTask('g_a')->systemRejectedWithoutSubmission()->create();
    $submitted = legacyRecord($this->branch, BriefingPeriod::Daily, '2026-09-01', ['g_a' => approvedBy($this->reviewer), 'g_b' => approvedBy($this->reviewer)]);

    artisan('briefing:group-legacy-bundles --execute --snapshot=grouping/slots.jsonl')->assertSuccessful();

    $bundle = $submitted->refresh()->load('items');

    expect($withSlot->id)->toBeLessThan($submitted->id)
        ->and($bundle->status)->toBe(BriefingBundleStatus::Approved)
        ->and($bundle->items->pluck('task_key')->sort()->values()->all())->toBe(['g_a', 'g_b'])
        ->and($slot->refresh()->briefing_record_id)->toBe($withSlot->id)
        ->and($withSlot->refresh()->status)->toBeNull()
        ->and($bundle->transitions()->first()->metadata['left_in_source_item_ids'])->toBe([$slot->id]);

    artisan('briefing:group-legacy-bundles --restore=grouping/slots.jsonl')->assertSuccessful();

    expect($submitted->refresh()->status)->toBeNull()
        ->and($slot->refresh()->briefing_record_id)->toBe($withSlot->id);
});

it('sends a group to manual review when every row holds a duplicate slot', function () {
    $first = legacyRecord($this->branch, BriefingPeriod::Daily, '2026-09-01', ['g_a' => approvedBy($this->reviewer)]);
    BriefingItem::factory()->for($first, 'record')->forTask('g_b')->systemRejectedWithoutSubmission()->create();
    $second = legacyRecord($this->branch, BriefingPeriod::Daily, '2026-09-01', ['g_b' => approvedBy($this->reviewer)]);
    BriefingItem::factory()->for($second, 'record')->forTask('g_a')->systemRejectedWithoutSubmission()->create();

    artisan('briefing:group-legacy-bundles')->expectsOutputToContain('manual_review (duplicate_task)')->assertSuccessful();
    artisan('briefing:group-legacy-bundles --execute --snapshot=grouping/both.jsonl')->assertSuccessful();

    expect(BriefingRecord::whereNotNull('status')->count())->toBe(0);
});

it('does not require a point that was added after the day it is judging', function () {
    groupingTask('g_new', createdAt: '2026-09-10 09:00:00');
    $record = legacyRecord($this->branch, BriefingPeriod::Daily, '2026-09-01', ['g_a' => approvedBy($this->reviewer), 'g_b' => approvedBy($this->reviewer)]);

    artisan('briefing:group-legacy-bundles --execute --snapshot=grouping/added.jsonl')->assertSuccessful();

    $bundle = $record->refresh()->load('requirements');

    expect($bundle->status)->toBe(BriefingBundleStatus::Approved)
        ->and($bundle->requirements->pluck('task_key')->sort()->values()->all())->toBe(['g_a', 'g_b']);
});

it('still requires a point that existed on the day but was not submitted', function () {
    groupingTask('g_c', createdAt: '2026-08-01 09:00:00');
    $record = legacyRecord($this->branch, BriefingPeriod::Daily, '2026-09-01', ['g_a' => approvedBy($this->reviewer), 'g_b' => approvedBy($this->reviewer)]);

    artisan('briefing:group-legacy-bundles --execute --snapshot=grouping/existing.jsonl')->assertSuccessful();

    expect($record->refresh()->status)->toBe(BriefingBundleStatus::Draft);
});

it('accepts a point that has since been retired when the day holds it, and ignores retired points it does not hold', function () {
    groupingTask('g_old')->update(['is_active' => false]);
    groupingTask('g_gone')->update(['is_active' => false]);
    $record = legacyRecord($this->branch, BriefingPeriod::Daily, '2026-09-01', [
        'g_a' => approvedBy($this->reviewer),
        'g_b' => approvedBy($this->reviewer),
        'g_old' => approvedBy($this->reviewer),
    ]);

    artisan('briefing:group-legacy-bundles --execute --snapshot=grouping/retired.jsonl')->assertSuccessful();

    $bundle = $record->refresh()->load('requirements');

    expect($bundle->status)->toBe(BriefingBundleStatus::Approved)
        ->and($bundle->requirements->pluck('task_key')->sort()->values()->all())->toBe(['g_a', 'g_b', 'g_old']);
});

it('uses the global points for days before the branch got its own points', function () {
    groupingTask('g_branch', createdAt: '2026-09-15 09:00:00', branchId: $this->branch->id);
    $before = legacyRecord($this->branch, BriefingPeriod::Daily, '2026-09-01', ['g_a' => approvedBy($this->reviewer), 'g_b' => approvedBy($this->reviewer)]);
    $after = legacyRecord($this->branch, BriefingPeriod::Daily, '2026-09-20', ['g_branch' => approvedBy($this->reviewer, '2026-09-21 10:00:00')]);

    artisan('briefing:group-legacy-bundles --execute --snapshot=grouping/scope.jsonl')->assertSuccessful();

    expect($before->refresh()->status)->toBe(BriefingBundleStatus::Approved)
        ->and($after->refresh()->status)->toBe(BriefingBundleStatus::Approved)
        ->and($after->requirements()->pluck('task_key')->all())->toBe(['g_branch']);
});

it('never converts a bundle that already exists on the key', function () {
    $legacy = legacyRecord($this->branch, BriefingPeriod::Daily, '2026-09-01', ['g_a' => [], 'g_b' => []]);
    BriefingRecord::factory()->forBranch($this->branch)->onDate('2026-09-01')->create(['period_start' => '2026-09-01', 'status' => BriefingBundleStatus::Draft->value]);

    artisan('briefing:group-legacy-bundles --execute --snapshot=grouping/collision.jsonl')
        ->expectsOutputToContain('existing_bundle_collision')
        ->assertSuccessful();

    expect($legacy->refresh()->isLegacyUngrouped())->toBeTrue();
});

it('keeps synthetic rejections out of bundles as cleanup candidates', function () {
    $record = legacyRecord($this->branch, BriefingPeriod::Daily, '2026-09-01', []);
    BriefingItem::factory()->for($record, 'record')->forTask('g_a')->systemRejectedWithoutSubmission()->create();

    artisan('briefing:group-legacy-bundles --execute --snapshot=grouping/synthetic.jsonl')
        ->expectsOutputToContain('cleanup_candidate')
        ->assertSuccessful();

    expect($record->refresh()->isLegacyUngrouped())->toBeTrue();
});

it('backfills completion on legacy ticked checkboxes from the row itself', function () {
    groupingTask('g_check', BriefingPeriod::Daily, 'checkbox');
    BriefingTask::where('key', 'g_b')->update(['is_active' => false]);
    BriefingTask::clearCache();
    $record = legacyRecord($this->branch, BriefingPeriod::Daily, '2026-09-01', ['g_a' => [], 'g_check' => ['is_completed' => false, 'completed_at' => null, 'photo_paths' => null]]);
    $checkbox = $record->items()->where('task_key', 'g_check')->sole();

    artisan('briefing:group-legacy-bundles --execute --snapshot=grouping/checkbox.jsonl')->assertSuccessful();

    expect($record->refresh()->status)->toBe(BriefingBundleStatus::SupervisorReview)
        ->and($checkbox->refresh()->is_completed)->toBeTrue()
        ->and($checkbox->completed_at->equalTo($checkbox->created_at))->toBeTrue();
});

it('resumes an interrupted run by appending to the same snapshot', function () {
    legacyRecord($this->branch, BriefingPeriod::Daily, '2026-09-01', ['g_a' => [], 'g_b' => []]);
    artisan('briefing:group-legacy-bundles --execute --snapshot=grouping/resume.jsonl')->assertSuccessful();

    legacyRecord($this->branch, BriefingPeriod::Daily, '2026-09-02', ['g_a' => [], 'g_b' => []]);

    artisan('briefing:group-legacy-bundles --execute --snapshot=grouping/resume.jsonl')->assertFailed();
    artisan('briefing:group-legacy-bundles --execute --resume --snapshot=grouping/resume.jsonl')->assertSuccessful();

    expect(substr_count(trim(Storage::disk('local')->get('grouping/resume.jsonl')), "\n"))->toBe(1)
        ->and(BriefingRecord::whereNotNull('status')->count())->toBe(2);
});

it('restores every converted group exactly from the snapshot', function () {
    groupingTask('g_w1', BriefingPeriod::Weekly);
    groupingTask('g_w2', BriefingPeriod::Weekly);
    $tuesday = legacyRecord($this->branch, BriefingPeriod::Weekly, '2026-09-08', ['g_w1' => []]);
    $thursday = legacyRecord($this->branch, BriefingPeriod::Weekly, '2026-09-10', ['g_w2' => []]);
    $movedItem = $thursday->items()->sole();

    artisan('briefing:group-legacy-bundles --execute --snapshot=grouping/restore.jsonl')->assertSuccessful();
    artisan('briefing:group-legacy-bundles --restore=grouping/restore.jsonl')->assertSuccessful();

    expect($tuesday->refresh()->isLegacyUngrouped())->toBeTrue()
        ->and($tuesday->period_start)->toBeNull()
        ->and($movedItem->refresh()->briefing_record_id)->toBe($thursday->id)
        ->and($movedItem->submitted_by)->toBeNull()
        ->and(BriefingRecordRequirement::count())->toBe(0)
        ->and(BriefingRecordTransition::count())->toBe(0);
});
