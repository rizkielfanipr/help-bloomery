<?php

use App\Models\Branch;
use App\Models\BriefingBranchSetting;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use App\Models\BriefingScore;
use App\Models\BriefingSettings;
use App\Models\BriefingTask;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * docs/products/daily-briefing/scoring-ui/prd.md §13/§18.3/Phase 13. Dry-run by default; mutation only
 * behind --confirm; every overwritten row is snapshotted first; --restore reverts from it.
 */
beforeEach(function () {
    Storage::fake('local');
    BriefingSettings::instance()->update(['scoring_started_at' => '2020-01-01']);
});

it('requires both ends of the date range', function () {
    $this->artisan('briefing:recompute-history', ['--dry-run' => true])->assertFailed();
});

it('refuses to run without --dry-run or --confirm', function () {
    $this->artisan('briefing:recompute-history', [
        '--from-year' => 2026, '--from-month' => 6, '--to-year' => 2026, '--to-month' => 6,
    ])->assertFailed();
});

it('dry-run never mutates briefing_scores', function () {
    $branch = Branch::factory()->create();
    BriefingBranchSetting::factory()->create(['branch_id' => $branch->id]);
    BriefingTask::create(['key' => 'rh_task', 'label' => 'Task', 'period' => 'daily', 'submission_type' => 'camera_only', 'is_active' => true, 'include_in_score' => true, 'sort_order' => 1]);

    $beforeCount = BriefingScore::count();

    $this->artisan('briefing:recompute-history', [
        '--from-year' => 2026, '--from-month' => 6, '--to-year' => 2026, '--to-month' => 6,
        '--dry-run' => true,
    ])->assertSuccessful();

    expect(BriefingScore::count())->toBe($beforeCount);
    Storage::disk('local')->assertDirectoryEmpty('briefing-recompute-snapshots');
});

it('snapshots the old row before overwriting it when run with --confirm', function () {
    $branch = Branch::factory()->create();
    BriefingBranchSetting::factory()->create(['branch_id' => $branch->id]);
    $task = BriefingTask::create(['key' => 'rh_task_2', 'label' => 'Task', 'period' => 'daily', 'submission_type' => 'camera_only', 'is_active' => true, 'include_in_score' => true, 'sort_order' => 1]);

    $old = BriefingScore::create([
        'branch_id' => $branch->id, 'year' => 2026, 'month' => 6, 'score' => 10.0,
        'breakdown' => ['method_version' => 1], 'computed_at' => now(),
    ]);

    $staff = User::factory()->create(['is_active' => true, 'branch_id' => $branch->id]);
    $record = BriefingRecord::create(['user_id' => $staff->id, 'branch_id' => $branch->id, 'period' => 'daily', 'record_date' => '2026-06-01']);
    BriefingItem::create(['briefing_record_id' => $record->id, 'task_key' => $task->key, 'is_completed' => true, 'completed_at' => '2026-06-01', 'review_status' => 'approved']);

    $this->artisan('briefing:recompute-history', [
        '--from-year' => 2026, '--from-month' => 6, '--to-year' => 2026, '--to-month' => 6,
        '--confirm' => true,
    ])->assertSuccessful();

    expect($old->fresh()->score)->not->toBe(10.0);

    $files = Storage::disk('local')->allFiles('briefing-recompute-snapshots');
    expect($files)->toHaveCount(1);

    $snapshot = json_decode(Storage::disk('local')->get($files[0]), true);
    expect($snapshot)->toHaveCount(1)
        ->and((float) $snapshot[0]['score'])->toBe(10.0);
});

it('restores a score from a snapshot file', function () {
    $branch = Branch::factory()->create();
    $score = BriefingScore::create([
        'branch_id' => $branch->id, 'year' => 2026, 'month' => 6, 'score' => 42.0,
        'breakdown' => ['method_version' => 2], 'computed_at' => now(),
    ]);

    $snapshotPath = 'briefing-recompute-snapshots/test-snapshot.json';
    Storage::disk('local')->put($snapshotPath, json_encode([
        ['branch_id' => $branch->id, 'year' => 2026, 'month' => 6, 'score' => 99.0, 'breakdown' => ['method_version' => 1], 'computed_at' => now()->toIso8601String()],
    ]));

    $this->artisan('briefing:recompute-history', ['--restore' => $snapshotPath])->assertSuccessful();

    expect($score->fresh()->score)->toBe(99.0);
});
