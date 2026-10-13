<?php

use App\Enums\BriefingBundleStatus;
use App\Models\Branch;
use App\Models\BriefingBranchSetting;
use App\Models\BriefingRecord;
use App\Models\BriefingRecordRequirement;
use App\Models\BriefingScore;
use App\Models\BriefingSettings;
use App\Models\BriefingTask;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\artisan;

/**
 * docs/daily-briefing-bundle-approval-prd.md Phase 10: the per-item vs bundle comparison is read-only
 * and explains its deltas; history recompute previews any method but only ever writes the active
 * one, behind --confirm, with a snapshot that restores the old values.
 */
beforeEach(function () {
    Cache::flush();
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-10-14 10:00:00'));
    BriefingSettings::instance()->update(['scoring_started_at' => '2020-01-01', 'passing_score' => 80]);

    $this->branch = Branch::factory()->create(['name' => 'Cabang Compare']);
    BriefingBranchSetting::factory()->create(['branch_id' => $this->branch->id, 'started_at' => '2025-01-01']);
    BriefingTask::create(['key' => 'compare_point', 'label' => 'Poin', 'period' => 'daily', 'submission_type' => 'camera_only', 'is_active' => true, 'include_in_score' => true, 'sort_order' => 1]);

    foreach (range(1, 15) as $day) {
        $bundle = BriefingRecord::factory()->forBranch($this->branch)->onDate(sprintf('2026-09-%02d', $day))->create([
            'period_start' => sprintf('2026-09-%02d', $day),
            'status' => BriefingBundleStatus::Approved->value,
        ]);
        BriefingRecordRequirement::factory()->for($bundle, 'record')->create(['task_key' => 'compare_point']);
    }

    BriefingRecord::factory()->forBranch($this->branch)->onDate('2026-09-20')->create();

    $this->stored = BriefingScore::create([
        'branch_id' => $this->branch->id,
        'year' => 2026,
        'month' => 9,
        'score' => 90,
        'breakdown' => ['method_version' => 2, 'passing_score' => 80, 'periods' => []],
        'computed_at' => now(),
    ]);
});

it('compares the stored per-item score with the bundle method without writing anything', function () {
    $exitCode = Artisan::call('briefing:compare-scores', ['--year' => 2026, '--month' => 9, '--method' => 3]);
    $output = Artisan::output();
    $row = collect(explode("\n", $output))->first(fn (string $line): bool => str_contains($line, 'Cabang Compare'));

    expect($exitCode)->toBe(0)
        ->and($row)->toContain('90.00')
        ->toContain('v2')
        ->toContain('50.00')
        ->toContain('-40.00')
        ->toContain('15/30 · - · -')
        ->toContain('| 15 ')
        ->toContain('1 / 0')
        ->and($output)->toContain('outlier (|delta| ≥ 5): 1')->toContain('read-only');

    expect((float) $this->stored->refresh()->score)->toBe(90.0)
        ->and($this->stored->methodVersion())->toBe(2)
        ->and(BriefingScore::count())->toBe(1);
});

it('rejects an unknown method', function () {
    artisan('briefing:compare-scores --year=2026 --month=9 --method=9')->assertFailed();
});

it('previews the bundle method in a history dry run without writing', function () {
    artisan('briefing:recompute-history --from-year=2026 --from-month=9 --to-year=2026 --to-month=9 --dry-run --method=3')
        ->expectsOutputToContain('metode v3')
        ->expectsOutputToContain('50.00')
        ->assertSuccessful();

    expect((float) $this->stored->refresh()->score)->toBe(90.0);
});

it('refuses to write a method other than the active one', function () {
    artisan('briefing:recompute-history --from-year=2026 --from-month=9 --to-year=2026 --to-month=9 --confirm --method=3')->assertFailed();

    expect((float) $this->stored->refresh()->score)->toBe(90.0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('writes the active bundle method behind --confirm and restores the old values from its snapshot', function () {
    config(['briefing.scoring_method_version' => 3]);

    artisan('briefing:recompute-history --from-year=2026 --from-month=9 --to-year=2026 --to-month=9 --confirm')->assertSuccessful();

    expect((float) $this->stored->refresh()->score)->toBe(50.0)
        ->and($this->stored->methodVersion())->toBe(3);

    $snapshot = collect(Storage::disk('local')->allFiles('briefing-recompute-snapshots'))->sole();

    artisan("briefing:recompute-history --restore={$snapshot}")->assertSuccessful();

    expect((float) $this->stored->refresh()->score)->toBe(90.0)
        ->and($this->stored->methodVersion())->toBe(2);
});
