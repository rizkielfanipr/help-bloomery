<?php

use App\Enums\BriefingReviewStatus;
use App\Models\Branch;
use App\Models\BriefingBranchSetting;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use App\Models\BriefingSettings;
use App\Models\BriefingTask;
use App\Models\User;
use App\Services\BriefingScoreCalculator;
use App\Services\BriefingScoreResult;
use Carbon\Carbon;

/**
 * docs/products/daily-briefing/scoring-ui/prd.md §7-§10/§21 (Phase 2/14). Calculator is tested directly,
 * independent of the compute-scores command/UI.
 */
function scoreParticipantBranch(array $settingOverrides = []): Branch
{
    $branch = Branch::factory()->create();
    BriefingBranchSetting::factory()->create(array_merge(['branch_id' => $branch->id], $settingOverrides));

    return $branch;
}

function scoreTask(array $overrides = []): BriefingTask
{
    static $n = 0;
    $n++;

    return BriefingTask::create(array_merge([
        'key' => 'calc_test_task_'.$n,
        'label' => 'Calc Test Task '.$n,
        'period' => 'daily',
        'submission_type' => 'camera_only',
        'is_active' => true,
        'include_in_score' => true,
        'sort_order' => $n,
    ], $overrides));
}

function recordOccurrence(Branch $branch, string $taskKey, string $period, string $date, BriefingReviewStatus $status): BriefingItem
{
    $user = User::factory()->create(['is_active' => true, 'branch_id' => $branch->id]);

    $record = BriefingRecord::create([
        'user_id' => $user->id,
        'branch_id' => $branch->id,
        'period' => $period,
        'record_date' => $date,
        'submitted_at' => $date,
    ]);

    return BriefingItem::create([
        'briefing_record_id' => $record->id,
        'task_key' => $taskKey,
        'is_completed' => true,
        'completed_at' => $date,
        'review_status' => $status->value,
    ]);
}

beforeEach(function () {
    BriefingSettings::instance()->update(['scoring_started_at' => '2020-01-01', 'passing_score' => 80]);
});

it('computes approved/expected and rate for a fully-elapsed past month', function () {
    $branch = scoreParticipantBranch();
    $task = scoreTask(['period' => 'daily']);

    // 30-day month entirely in the past; approve 27 of 30 days.
    foreach (range(1, 30) as $day) {
        $status = $day <= 27 ? BriefingReviewStatus::Approved : BriefingReviewStatus::Rejected;
        recordOccurrence($branch, $task->key, 'daily', Carbon::create(2026, 6, $day)->toDateString(), $status);
    }

    $result = app(BriefingScoreCalculator::class)->calculate($branch, 2026, 6);

    expect($result->periods['daily']['approved'])->toBe(27)
        ->and($result->periods['daily']['expected'])->toBe(30)
        ->and($result->periods['daily']['rate'])->toBe(90.0)
        ->and($result->score)->toBe(90.0)
        ->and($result->status)->toBe(BriefingScoreResult::STATUS_ACHIEVED)
        ->and($result->isProvisional)->toBeFalse();
});

it('gives missing, rejected, and pending zero credit but still counts them as expected once due', function () {
    $branch = scoreParticipantBranch();
    $task = scoreTask();

    recordOccurrence($branch, $task->key, 'daily', '2026-06-01', BriefingReviewStatus::Approved);
    recordOccurrence($branch, $task->key, 'daily', '2026-06-02', BriefingReviewStatus::Rejected);
    recordOccurrence($branch, $task->key, 'daily', '2026-06-03', BriefingReviewStatus::SupervisorReview);
    // 2026-06-04: no submission at all -> missing, still expected.

    $result = app(BriefingScoreCalculator::class)->calculate($branch, 2026, 6);

    // June 2026 is entirely in the past relative to "today", so every one of its 30 days is due
    // and counted as expected regardless of which days actually have a submission.
    expect($result->periods['daily']['approved'])->toBe(1)
        ->and($result->periods['daily']['expected'])->toBe(30);
});

it('does not count future dates in the current month as expected, and flags the result provisional', function () {
    $branch = scoreParticipantBranch();
    $task = scoreTask();

    foreach (range(1, now()->day) as $day) {
        recordOccurrence($branch, $task->key, 'daily', Carbon::create(now()->year, now()->month, $day)->toDateString(), BriefingReviewStatus::Approved);
    }

    $result = app(BriefingScoreCalculator::class)->calculate($branch, now()->year, now()->month);

    expect($result->periods['daily']['expected'])->toBe(now()->day)
        ->and($result->periods['daily']['approved'])->toBe(now()->day)
        ->and($result->isProvisional)->toBeTrue()
        ->and($result->periodEnd)->toBe(now()->toDateString());
});

it('excludes days before the branch\'s own participation started_at from expected', function () {
    $branch = scoreParticipantBranch(['started_at' => '2026-06-15']);
    $task = scoreTask();

    recordOccurrence($branch, $task->key, 'daily', '2026-06-20', BriefingReviewStatus::Approved);

    $result = app(BriefingScoreCalculator::class)->calculate($branch, 2026, 6);

    // June has 30 days; only the 15th through the 30th (16 days) count.
    expect($result->periods['daily']['expected'])->toBe(16);
});

it('marks the whole result not_applicable for a branch that is not a participant at all', function () {
    $branch = Branch::factory()->create();
    scoreTask();

    $result = app(BriefingScoreCalculator::class)->calculate($branch, 2026, 6);

    expect($result->isNotApplicable())->toBeTrue()
        ->and($result->score)->toBe(0.0);
});

it('excludes a task with include_in_score=false entirely from the breakdown and the score', function () {
    $branch = scoreParticipantBranch();
    $scored = scoreTask(['include_in_score' => true]);
    $notScored = scoreTask(['include_in_score' => false]);

    foreach (range(1, 30) as $day) {
        recordOccurrence($branch, $scored->key, 'daily', Carbon::create(2026, 6, $day)->toDateString(), BriefingReviewStatus::Approved);
        recordOccurrence($branch, $notScored->key, 'daily', Carbon::create(2026, 6, $day)->toDateString(), BriefingReviewStatus::Rejected);
    }

    $result = app(BriefingScoreCalculator::class)->calculate($branch, 2026, 6);

    $taskKeys = collect($result->periods['daily']['tasks'])->pluck('key');

    expect($taskKeys)->toContain($scored->key)
        ->and($taskKeys)->not->toContain($notScored->key)
        // The excluded task was all-rejected; if it leaked in, the rate would drop below 100.
        ->and($result->periods['daily']['rate'])->toBe(100.0);
});

it('redistributes a period\'s weight proportionally when it has no applicable tasks', function () {
    $branch = scoreParticipantBranch();
    $daily = scoreTask(['period' => 'daily']);
    $weekly = scoreTask(['period' => 'weekly']);
    // No monthly task at all -> monthly period is not_applicable, its 30% weight redistributes.

    foreach (range(1, 30) as $day) {
        recordOccurrence($branch, $daily->key, 'daily', Carbon::create(2026, 6, $day)->toDateString(), BriefingReviewStatus::Approved);
    }
    foreach ([1, 8, 15, 22, 29] as $day) {
        recordOccurrence($branch, $weekly->key, 'weekly', Carbon::create(2026, 6, $day)->toDateString(), BriefingReviewStatus::Approved);
    }

    $result = app(BriefingScoreCalculator::class)->calculate($branch, 2026, 6);

    expect($result->periods['monthly']['applicable'])->toBeFalse()
        // Default weights 40/30/30; redistributed over daily+weekly (70 total):
        // daily -> 40/70*100 = 57.14, weekly -> 30/70*100 = 42.86 (docs §8.3 example).
        ->and($result->periods['daily']['effective_weight'])->toBe(57.14)
        ->and($result->periods['weekly']['effective_weight'])->toBe(42.86)
        ->and($result->score)->toBe(100.0);
});

it('applies the passing_score threshold with >= semantics at the boundary', function () {
    $branch = scoreParticipantBranch();
    $task = scoreTask();
    BriefingSettings::instance()->update(['passing_score' => 80]);

    // 24/30 = 80.0 exactly.
    foreach (range(1, 30) as $day) {
        $status = $day <= 24 ? BriefingReviewStatus::Approved : BriefingReviewStatus::Rejected;
        recordOccurrence($branch, $task->key, 'daily', Carbon::create(2026, 6, $day)->toDateString(), $status);
    }

    $result = app(BriefingScoreCalculator::class)->calculate($branch, 2026, 6);

    expect($result->score)->toBe(80.0)
        ->and($result->status)->toBe(BriefingScoreResult::STATUS_ACHIEVED);
});

it('gives at most one credit per occurrence even if two records exist for the same branch/date (legacy duplicate)', function () {
    $branch = scoreParticipantBranch();
    $task = scoreTask();

    $userA = User::factory()->create(['is_active' => true, 'branch_id' => $branch->id]);
    $userB = User::factory()->create(['is_active' => true, 'branch_id' => $branch->id]);

    $recordA = BriefingRecord::create(['user_id' => $userA->id, 'branch_id' => $branch->id, 'period' => 'daily', 'record_date' => '2026-06-01']);
    BriefingItem::create(['briefing_record_id' => $recordA->id, 'task_key' => $task->key, 'is_completed' => true, 'completed_at' => '2026-06-01', 'review_status' => 'rejected']);

    $recordB = BriefingRecord::create(['user_id' => $userB->id, 'branch_id' => $branch->id, 'period' => 'daily', 'record_date' => '2026-06-01']);
    BriefingItem::create(['briefing_record_id' => $recordB->id, 'task_key' => $task->key, 'is_completed' => true, 'completed_at' => '2026-06-01', 'review_status' => 'approved']);

    $result = app(BriefingScoreCalculator::class)->calculate($branch, 2026, 6);

    expect($result->periods['daily']['approved'])->toBe(1);
});

it('is deterministic: calculating the same branch/month twice yields identical output', function () {
    $branch = scoreParticipantBranch();
    $task = scoreTask();
    recordOccurrence($branch, $task->key, 'daily', '2026-06-01', BriefingReviewStatus::Approved);

    $calculator = app(BriefingScoreCalculator::class);
    $first = $calculator->calculate($branch, 2026, 6)->toArray();
    $second = $calculator->calculate($branch, 2026, 6)->toArray();

    expect($first)->toBe($second);
});
