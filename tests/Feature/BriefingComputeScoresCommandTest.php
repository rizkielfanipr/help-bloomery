<?php

use App\Enums\BriefingReviewStatus;
use App\Models\Branch;
use App\Models\BriefingBranchSetting;
use App\Models\BriefingItem;
use App\Models\BriefingPeriodWeight;
use App\Models\BriefingRecord;
use App\Models\BriefingScore;
use App\Models\BriefingSettings;
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

beforeEach(function () {
    BriefingSettings::instance()->update(['scoring_started_at' => '2024-01-01']);
});

it('computes a weighted score using the global default period weights, redistributing weight away from a period with no scoreable tasks', function () {
    $branch = Branch::factory()->create(['is_active' => true]);
    BriefingBranchSetting::factory()->create(['branch_id' => $branch->id]);
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
    expect($score->breakdown['periods']['monthly']['applicable'])->toBeFalse()
        ->and($score->breakdown['periods']['daily']['effective_weight'])->toBe(57.14)
        ->and($score->breakdown['periods']['weekly']['effective_weight'])->toBe(42.86)
        ->and($score->breakdown['periods']['daily']['rate'])->toEqual(50.0)
        ->and($score->breakdown['periods']['weekly']['rate'])->toEqual(100.0)
        ->and($score->score)->toBe(71.43);
});

it('uses a branch-specific period weight override instead of the global default', function () {
    $branch = Branch::factory()->create(['is_active' => true]);
    BriefingBranchSetting::factory()->create(['branch_id' => $branch->id]);
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
    expect($score->breakdown['periods']['daily']['effective_weight'])->toEqual(100.0)
        ->and($score->breakdown['periods']['daily']['configured_weight'])->toEqual(80.0)
        ->and($score->score)->toBe(100.0);
});

it(
    'excludes a task with include_in_score=false from the breakdown and the score entirely '
    .'(contract change from docs/daily-briefing-scoring-ui-prd.md §10 — method_version 1 included '
    .'every active task in the breakdown and its rate regardless of include_in_score)',
    function () {
        $branch = Branch::factory()->create(['is_active' => true]);
        BriefingBranchSetting::factory()->create(['branch_id' => $branch->id]);
        $user = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);

        BriefingTask::create(['key' => 'scored', 'label' => 'Scored', 'period' => 'daily', 'submission_type' => 'camera_only', 'is_active' => true, 'include_in_score' => true, 'sort_order' => 1]);
        BriefingTask::create(['key' => 'not_scored', 'label' => 'Not Scored', 'period' => 'daily', 'submission_type' => 'text_only', 'is_active' => true, 'include_in_score' => false, 'sort_order' => 2]);

        $year = 2026;
        $month = 6;
        $daysInMonth = Carbon::create($year, $month)->daysInMonth;

        for ($d = 1; $d <= $daysInMonth; $d++) {
            createDailyBriefingApproval($user, Carbon::create($year, $month, $d), 'scored');
            // "not_scored" is left REJECTED every day — if it leaked into the formula the score
            // would drop well below 100, proving it was actually excluded rather than coincidentally neutral.
            BriefingItem::create([
                'briefing_record_id' => BriefingRecord::where('user_id', $user->id)
                    ->whereDate('record_date', Carbon::create($year, $month, $d))
                    ->value('id'),
                'task_key' => 'not_scored',
                'is_completed' => true,
                'completed_at' => Carbon::create($year, $month, $d),
                'review_status' => BriefingReviewStatus::Rejected->value,
            ]);
        }

        $this->artisan('briefing:compute-scores', ['--year' => $year, '--month' => $month, '--branch' => $branch->id])
            ->assertSuccessful();

        $score = BriefingScore::where('branch_id', $branch->id)->where('year', $year)->where('month', $month)->first();

        $taskKeys = collect($score->breakdown['periods']['daily']['tasks'])->pluck('key');

        expect($taskKeys)->toContain('scored')
            ->and($taskKeys)->not->toContain('not_scored')
            ->and($score->score)->toBe(100.0);
    }
);

it('scores the branch task that replaces a scoreable global task', function () {
    $branch = Branch::factory()->create(['is_active' => true]);
    BriefingBranchSetting::factory()->create(['branch_id' => $branch->id]);
    $user = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);

    BriefingTask::create(['key' => 'daily_selfie', 'label' => 'Global Selfie', 'period' => 'daily', 'submission_type' => 'camera_only', 'is_active' => true, 'include_in_score' => true, 'sort_order' => 1]);
    BriefingTask::create(['branch_id' => $branch->id, 'key' => 'daily_selfie_branch', 'label' => 'Branch Selfie', 'period' => 'daily', 'submission_type' => 'camera_only', 'is_active' => true, 'include_in_score' => true, 'sort_order' => 1]);

    $year = 2026;
    $month = 8;
    $daysInMonth = Carbon::create($year, $month)->daysInMonth;

    for ($day = 1; $day <= $daysInMonth; $day++) {
        createDailyBriefingApproval($user, Carbon::create($year, $month, $day), 'daily_selfie_branch');
    }

    $this->artisan('briefing:compute-scores', ['--year' => $year, '--month' => $month, '--branch' => $branch->id])
        ->assertSuccessful();

    $score = BriefingScore::where('branch_id', $branch->id)->where('year', $year)->where('month', $month)->firstOrFail();

    expect($score->score)->toBe(100.0)
        ->and($score->breakdown['periods']['daily']['tasks'][0]['key'])->toBe('daily_selfie_branch')
        ->and($score->breakdown['periods']['daily']['tasks'][0]['approved'])->toBe($daysInMonth);
});

it('uses the recorded branch and includes historical records from inactive or transferred users', function () {
    $originalBranch = Branch::factory()->create(['is_active' => true]);
    BriefingBranchSetting::factory()->create(['branch_id' => $originalBranch->id]);
    $newBranch = Branch::factory()->create(['is_active' => true]);
    $user = User::factory()->create(['branch_id' => $originalBranch->id, 'is_active' => true]);

    BriefingTask::create(['key' => 'daily_historical', 'label' => 'Daily Historical', 'period' => 'daily', 'submission_type' => 'camera_only', 'is_active' => true, 'include_in_score' => true, 'sort_order' => 1]);

    $date = Carbon::create(2026, 7, 1);
    createDailyBriefingApproval($user, $date, 'daily_historical');

    $user->update(['branch_id' => $newBranch->id, 'is_active' => false]);

    $this->artisan('briefing:compute-scores', ['--year' => 2026, '--month' => 7, '--branch' => $originalBranch->id])
        ->assertSuccessful();

    $score = BriefingScore::where('branch_id', $originalBranch->id)->where('year', 2026)->where('month', 7)->firstOrFail();

    expect($score->breakdown['periods']['daily']['tasks'][0]['approved'])->toBe(1)
        ->and($score->score)->toBe(3.23);
});

it('starts the first scoring month from the configured launch date', function () {
    $branch = Branch::factory()->create(['is_active' => true]);
    BriefingBranchSetting::factory()->create(['branch_id' => $branch->id]);
    $user = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);

    BriefingSettings::instance()->update(['scoring_started_at' => '2026-07-22']);
    BriefingTask::create(['key' => 'daily_launch', 'label' => 'Daily Launch', 'period' => 'daily', 'submission_type' => 'camera_only', 'is_active' => true, 'include_in_score' => true, 'sort_order' => 1]);

    foreach (range(22, 31) as $day) {
        createDailyBriefingApproval($user, Carbon::create(2026, 7, $day), 'daily_launch');
    }

    $this->artisan('briefing:compute-scores', ['--year' => 2026, '--month' => 7, '--branch' => $branch->id])
        ->assertSuccessful();

    $score = BriefingScore::where('branch_id', $branch->id)->where('year', 2026)->where('month', 7)->firstOrFail();
    $daily = $score->breakdown['periods']['daily'];

    expect($score->score)->toBe(100.0)
        ->and($score->breakdown['period_start'])->toBe('2026-07-22')
        ->and($score->breakdown['period_end'])->toBe('2026-07-31')
        ->and($daily['tasks'][0]['expected'])->toBe(10)
        ->and($daily['tasks'][0]['approved'])->toBe(10);
});

it('does nothing for a branch with no scoreable tasks', function () {
    $branch = Branch::factory()->create(['is_active' => true]);
    BriefingBranchSetting::factory()->create(['branch_id' => $branch->id]);
    User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);

    $this->artisan('briefing:compute-scores', ['--year' => 2026, '--month' => 7, '--branch' => $branch->id])
        ->assertSuccessful();

    expect(BriefingScore::where('branch_id', $branch->id)->exists())->toBeFalse();
});

it('does not compute a score for a branch that is not an enabled Daily Briefing participant (contract change: '
    .'docs/daily-briefing-remediation-prd.md §14.1/§27 — a non-participant branch never gets a new score)',
    function () {
        $branch = Branch::factory()->create(['is_active' => true]);
        $user = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);

        BriefingTask::create(['key' => 'daily_nonparticipant', 'label' => 'Daily', 'period' => 'daily', 'submission_type' => 'camera_only', 'is_active' => true, 'include_in_score' => true, 'sort_order' => 1]);
        createDailyBriefingApproval($user, Carbon::create(2026, 9, 1), 'daily_nonparticipant');

        $this->artisan('briefing:compute-scores', ['--year' => 2026, '--month' => 9, '--branch' => $branch->id])
            ->assertSuccessful();

        expect(BriefingScore::where('branch_id', $branch->id)->exists())->toBeFalse();
    });

it('only counts days on/after the branch\'s own participation started_at as expected (docs/daily-briefing-remediation-prd.md §14.1)', function () {
    // The current month only counts up to today, so the clock is fixed inside the seeded month.
    $this->travelTo(Carbon::create(2026, 10, 20, 12));
    $branch = Branch::factory()->create(['is_active' => true]);
    BriefingBranchSetting::factory()->startingOn('2026-10-10')->create(['branch_id' => $branch->id]);
    $user = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);

    BriefingTask::create(['key' => 'daily_branch_start', 'label' => 'Daily', 'period' => 'daily', 'submission_type' => 'camera_only', 'is_active' => true, 'include_in_score' => true, 'sort_order' => 1]);

    foreach (range(1, 20) as $day) {
        createDailyBriefingApproval($user, Carbon::create(2026, 10, $day), 'daily_branch_start');
    }

    $this->artisan('briefing:compute-scores', ['--year' => 2026, '--month' => 10, '--branch' => $branch->id])
        ->assertSuccessful();

    $score = BriefingScore::where('branch_id', $branch->id)->where('year', 2026)->where('month', 10)->firstOrFail();

    // Days 1-9 predate the branch's own started_at (10th) and must not be counted as expected,
    // even though the global scoring_started_at is earlier: 10th through today (20th) = 11 days.
    expect($score->breakdown['periods']['daily']['tasks'][0]['expected'])->toBe(11);
});

it('does not count future dates in the current month as expected', function () {
    $branch = Branch::factory()->create(['is_active' => true]);
    BriefingBranchSetting::factory()->create(['branch_id' => $branch->id]);
    $user = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);

    BriefingTask::create(['key' => 'daily_current_month', 'label' => 'Daily', 'period' => 'daily', 'submission_type' => 'camera_only', 'is_active' => true, 'include_in_score' => true, 'sort_order' => 1]);

    $year = now()->year;
    $month = now()->month;

    foreach (range(1, now()->day) as $day) {
        createDailyBriefingApproval($user, Carbon::create($year, $month, $day), 'daily_current_month');
    }

    $this->artisan('briefing:compute-scores', ['--year' => $year, '--month' => $month, '--branch' => $branch->id])
        ->assertSuccessful();

    $score = BriefingScore::where('branch_id', $branch->id)->where('year', $year)->where('month', $month)->firstOrFail();

    expect($score->breakdown['periods']['daily']['tasks'][0]['expected'])->toBe(now()->day)
        ->and($score->score)->toBe(100.0);
});
