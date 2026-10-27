<?php

use App\Enums\BriefingReviewStatus;
use App\Models\Branch;
use App\Models\BriefingBranchSetting;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use App\Models\BriefingScore;
use App\Models\BriefingTask;
use App\Models\User;
use Carbon\Carbon;

/**
 * docs/products/daily-briefing/scoring-ui/prd.md §18.2/Phase 12: always read-only.
 */
it('requires --year and --month', function () {
    $this->artisan('briefing:compare-scores')->assertFailed();
});

it('never writes to briefing_scores', function () {
    $branch = Branch::factory()->create();
    BriefingBranchSetting::factory()->create(['branch_id' => $branch->id]);
    BriefingTask::create(['key' => 'compare_task', 'label' => 'Compare', 'period' => 'daily', 'submission_type' => 'camera_only', 'is_active' => true, 'include_in_score' => true, 'sort_order' => 1]);

    $beforeCount = BriefingScore::count();

    $this->artisan('briefing:compare-scores', ['--year' => 2026, '--month' => 6, '--branch' => $branch->id])
        ->assertSuccessful();

    expect(BriefingScore::count())->toBe($beforeCount);
});

it('reports a delta between the old stored score and what the current calculator produces', function () {
    $branch = Branch::factory()->create();
    BriefingBranchSetting::factory()->create(['branch_id' => $branch->id]);
    $task = BriefingTask::create(['key' => 'compare_task_2', 'label' => 'Compare', 'period' => 'daily', 'submission_type' => 'camera_only', 'is_active' => true, 'include_in_score' => true, 'sort_order' => 1]);

    $old = BriefingScore::create([
        'branch_id' => $branch->id,
        'year' => 2026,
        'month' => 6,
        'score' => 50.0,
        'breakdown' => ['method_version' => 1],
        'computed_at' => now(),
    ]);

    $user = User::factory()->create(['is_active' => true, 'branch_id' => $branch->id]);
    foreach (range(1, 30) as $day) {
        $record = BriefingRecord::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'period' => 'daily',
            'record_date' => Carbon::create(2026, 6, $day)->toDateString(),
        ]);
        BriefingItem::create([
            'briefing_record_id' => $record->id,
            'task_key' => $task->key,
            'is_completed' => true,
            'completed_at' => Carbon::create(2026, 6, $day),
            'review_status' => BriefingReviewStatus::Approved->value,
        ]);
    }

    $this->artisan('briefing:compare-scores', ['--year' => 2026, '--month' => 6, '--branch' => $branch->id])
        ->expectsOutputToContain('50.00')
        ->assertSuccessful();

    // Old row untouched.
    expect($old->fresh()->score)->toBe(50.0);
});
