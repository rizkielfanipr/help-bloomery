<?php

use App\Models\Branch;
use App\Models\BriefingTask;
use Database\Seeders\BriefingTasksSeeder;

/**
 * docs/products/daily-briefing/remediation/prd.md §16.2/§16.3/§25.5 (Phase 11). Contract change: the
 * seeder used to resolve "Toko Utama"/"Cabang Selatan" branch_id by exact name and silently fall
 * through to null when no such branch existed, upserting every one of those rows as an extra
 * *global* task (the `_2`/`_2_2`-suffixed duplicates visible in the current briefing_tasks data).
 * It must now skip that block entirely instead.
 */
it('does not create duplicate global tasks when "Toko Utama"/"Cabang Selatan" branches do not exist', function () {
    $this->seed(BriefingTasksSeeder::class);

    expect(BriefingTask::where('key', 'daily_selfie_pagi_2')->exists())->toBeFalse()
        ->and(BriefingTask::where('key', 'daily_selfie_pagi_2_2')->exists())->toBeFalse()
        ->and(BriefingTask::where('key', 'daily_selfie_pagi')->exists())->toBeTrue();

    $globalCount = BriefingTask::whereNull('branch_id')->count();

    expect($globalCount)->toBeLessThan(40);
});

it('is idempotent: running it twice does not change the task count', function () {
    $this->seed(BriefingTasksSeeder::class);
    $firstCount = BriefingTask::count();

    $this->seed(BriefingTasksSeeder::class);

    expect(BriefingTask::count())->toBe($firstCount);
});

it('seeds branch-specific tasks when the named branch does exist', function () {
    $branch = Branch::factory()->create(['name' => 'Toko Utama']);

    $this->seed(BriefingTasksSeeder::class);

    expect(BriefingTask::where('key', 'daily_selfie_pagi_2_2')->where('branch_id', $branch->id)->exists())->toBeTrue();
});
