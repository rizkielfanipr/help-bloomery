<?php

use App\Enums\BriefingReviewStatus;
use App\Models\Branch;
use App\Models\BriefingItem;
use App\Models\BriefingPeriodWeight;
use App\Models\BriefingRecord;
use App\Models\BriefingScore;
use App\Models\BriefingTask;
use App\Models\User;
use Carbon\Carbon;

function createDailyBriefingApproval(User $user, Carbon $date, string $taskKey): void
{
    $record = BriefingRecord::create([
        'user_id' => $user->id,
        'period' => 'daily',
        'record_date' => $date->toDateString(),
        'submitted_at' => $date,
    ]);

    BriefingItem::create([
        'briefing_record_id' => $record->id,
        'task_key' => $taskKey,
        'is_completed' => true,
        'completed_at' => $date,
        'review_status' => BriefingReviewStatus::Approved->value,
    ]);
}

function createWeeklyBriefingApproval(User $user, Carbon $monday, string $taskKey): void
{
    $record = BriefingRecord::create([
        'user_id' => $user->id,
        'period' => 'weekly',
        'record_date' => $monday->toDateString(),
        'submitted_at' => $monday,
    ]);

    BriefingItem::create([
        'briefing_record_id' => $record->id,
        'task_key' => $taskKey,
        'is_completed' => true,
        'completed_at' => $monday,
        'review_status' => BriefingReviewStatus::Approved->value,
    ]);
}

function mondaysIn(int $year, int $month): array
{
    $mondays = [];
    $current = Carbon::create($year, $month, 1);
    if (! $current->isMonday()) {
        $current->next(Carbon::MONDAY);
    }
    while ($current->month === $month) {
        $mondays[] = $current->copy();
        $current->addWeek();
    }

    return $mondays;
}

it('computes a weighted score using the global default period weights, redistributing weight away from a period with no scoreable tasks', function () {
    $branch = Branch::factory()->create(['is_active' => true]);
    $user = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);

    BriefingTask::create(['key' => 'daily_a', 'label' => 'Daily A', 'period' => 'daily', 'submission_type' => 'camera_only', 'is_active' => true, 'include_in_score' => true, 'sort_order' => 1]);
    BriefingTask::create(['key' => 'daily_b', 'label' => 'Daily B', 'period' => 'daily', 'submission_type' => 'camera_only', 'is_active' => true, 'include_in_score' => true, 'sort_order' => 2]);
    BriefingTask::create(['key' => 'weekly_a', 'label' => 'Weekly A', 'period' => 'weekly', 'submission_type' => 'camera_only', 'is_active' => true, 'include_in_score' => true, 'sort_order' => 1]);
    // No monthly scoreable task at all -> Monthly's weight must be redistributed to Daily/Weekly.

    $year = 2026;
    $month = 4;
    $daysInMonth = Carbon::create($year, $month)->daysInMonth;

    // daily_a approved every day (rate 100%), daily_b never approved (rate 0%) -> Daily period rate = 50%.
    for ($d = 1; $d <= $daysInMonth; $d++) {
        createDailyBriefingApproval($user, Carbon::create($year, $month, $d), 'daily_a');
    }

    // weekly_a approved every week (rate 100%).
    foreach (mondaysIn($year, $month) as $monday) {
        createWeeklyBriefingApproval($user, $monday, 'weekly_a');
    }

    $this->artisan('briefing:compute-scores', ['--year' => $year, '--month' => $month, '--branch' => $branch->id])
        ->assertSuccessful();

    $score = BriefingScore::where('branch_id', $branch->id)->where('year', $year)->where('month', $month)->first();

    expect($score)->not->toBeNull();

    // Global default is Daily 40 / Weekly 30 / Monthly 30. With no Monthly
    // tasks, effective weight is renormalized over Daily+Weekly (70 total):
    // Daily -> 40/70*100 = 57.14, Weekly -> 30/70*100 = 42.86.
    expect($score->breakdown)->not->toHaveKey('monthly')
        ->and($score->breakdown['daily']['effective_weight'])->toBe(57.14)
        ->and($score->breakdown['weekly']['effective_weight'])->toBe(42.86)
        ->and($score->breakdown['daily']['rate'])->toEqual(50.0)
        ->and($score->breakdown['weekly']['rate'])->toEqual(100.0)
        ->and($score->score)->toBe(71.43);
});

it('uses a branch-specific period weight override instead of the global default', function () {
    $branch = Branch::factory()->create(['is_active' => true]);
    $user = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);

    BriefingPeriodWeight::create([
        'branch_id' => $branch->id,
        'daily_weight' => 80,
        'weekly_weight' => 10,
        'monthly_weight' => 10,
    ]);

    BriefingTask::create(['key' => 'daily_only', 'label' => 'Daily Only', 'period' => 'daily', 'submission_type' => 'camera_only', 'is_active' => true, 'include_in_score' => true, 'sort_order' => 1]);

    $year = 2026;
    $month = 5;
    $daysInMonth = Carbon::create($year, $month)->daysInMonth;

    for ($d = 1; $d <= $daysInMonth; $d++) {
        createDailyBriefingApproval($user, Carbon::create($year, $month, $d), 'daily_only');
    }

    $this->artisan('briefing:compute-scores', ['--year' => $year, '--month' => $month, '--branch' => $branch->id])
        ->assertSuccessful();

    $score = BriefingScore::where('branch_id', $branch->id)->where('year', $year)->where('month', $month)->first();

    // Only Daily has a scoreable task, so its effective weight is 100% of
    // whatever the configured Daily weight is (renormalized alone) -> 100%.
    expect($score->breakdown['daily']['effective_weight'])->toEqual(100.0)
        ->and($score->breakdown['daily']['configured_weight'])->toEqual(80.0)
        ->and($score->score)->toBe(100.0);
});

it('excludes tasks that are not marked include_in_score', function () {
    $branch = Branch::factory()->create(['is_active' => true]);
    $user = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);

    BriefingTask::create(['key' => 'scored', 'label' => 'Scored', 'period' => 'daily', 'submission_type' => 'camera_only', 'is_active' => true, 'include_in_score' => true, 'sort_order' => 1]);
    BriefingTask::create(['key' => 'not_scored', 'label' => 'Not Scored', 'period' => 'daily', 'submission_type' => 'text_only', 'is_active' => true, 'include_in_score' => false, 'sort_order' => 2]);

    $year = 2026;
    $month = 6;
    $daysInMonth = Carbon::create($year, $month)->daysInMonth;

    for ($d = 1; $d <= $daysInMonth; $d++) {
        createDailyBriefingApproval($user, Carbon::create($year, $month, $d), 'scored');
    }

    $this->artisan('briefing:compute-scores', ['--year' => $year, '--month' => $month, '--branch' => $branch->id])
        ->assertSuccessful();

    $score = BriefingScore::where('branch_id', $branch->id)->where('year', $year)->where('month', $month)->first();

    $taskKeys = collect($score->breakdown['daily']['tasks'])->pluck('key');

    expect($taskKeys)->toContain('scored')
        ->and($taskKeys)->not->toContain('not_scored');
});

it('does nothing for a branch with no scoreable tasks', function () {
    $branch = Branch::factory()->create(['is_active' => true]);
    User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);

    $this->artisan('briefing:compute-scores', ['--year' => 2026, '--month' => 7, '--branch' => $branch->id])
        ->assertSuccessful();

    expect(BriefingScore::where('branch_id', $branch->id)->exists())->toBeFalse();
});
