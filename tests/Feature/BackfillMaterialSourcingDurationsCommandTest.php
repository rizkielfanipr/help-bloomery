<?php

use App\Enums\MaterialSampleCycleStatus;
use App\Enums\MaterialSourcingStatus;
use App\Models\RndMaterialAuditLog;
use App\Models\RndMaterialSampleCycle;
use App\Models\RndProductEsbMaterial;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

function backfillCycle(RndProductEsbMaterial $material, MaterialSampleCycleStatus $status, ?string $evaluatedAt, int $number = 1): RndMaterialSampleCycle
{
    return RndMaterialSampleCycle::query()->create([
        'rnd_product_esb_material_id' => $material->id,
        'cycle_number' => $number,
        'status' => $status,
        'evaluated_at' => $evaluatedAt,
    ]);
}

/** @return array<string, int> */
function runBackfill(array $options = []): array
{
    expect(Artisan::call('rnd:backfill-sourcing-durations', $options))->toBe(0);
    preg_match_all('/\|\s*([a-z_]+)\s*\|\s*(\d+)\s*\|/', Artisan::output(), $rows, PREG_SET_ORDER);

    return collect($rows)->mapWithKeys(fn (array $row): array => [$row[1] => (int) $row[2]])->all();
}

function backfillMaterial(array $attributes = []): RndProductEsbMaterial
{
    return RndProductEsbMaterial::factory()->legacy()->create([
        'sourcing_status' => MaterialSourcingStatus::Approved,
        'sourcing_requested_at' => '2026-10-01 08:00:00',
        'created_at' => '2026-09-01 08:00:00',
        ...$attributes,
    ]);
}

it('changes nothing in a dry-run and reports what apply would do', function () {
    $material = backfillMaterial(['finance_reviewed_at' => '2026-10-05 08:00:00']);
    $before = RndProductEsbMaterial::query()->orderBy('id')->get()->toArray();

    $summary = runBackfill();

    expect(RndProductEsbMaterial::query()->orderBy('id')->get()->toArray())->toBe($before)
        ->and($material->refresh()->sourcing_completed_at)->toBeNull()
        ->and($summary)->toMatchArray(['scanned' => 1, 'eligible' => 1, 'updated' => 0, 'completed_at_candidates' => 1]);
});

it('fills each final status from its own source', function () {
    $financeApproved = backfillMaterial(['finance_reviewed_at' => '2026-10-05 08:00:00', 'rnd_reviewed_at' => '2026-10-04 08:00:00']);
    $rndRejected = backfillMaterial(['sourcing_status' => MaterialSourcingStatus::Rejected, 'rnd_reviewed_at' => '2026-10-04 08:00:00', 'finance_reviewed_at' => '2026-10-09 08:00:00']);
    $sampleApproved = backfillMaterial(['sourcing_status' => MaterialSourcingStatus::ApprovedByRnd, 'rnd_reviewed_at' => '2026-10-20 08:00:00']);
    backfillCycle($sampleApproved, MaterialSampleCycleStatus::ResampleRequested, '2026-10-02 08:00:00', 1);
    backfillCycle($sampleApproved, MaterialSampleCycleStatus::Approved, '2026-10-06 08:00:00', 2);
    $sampleRejected = backfillMaterial(['sourcing_status' => MaterialSourcingStatus::RejectedByRnd]);
    backfillCycle($sampleRejected, MaterialSampleCycleStatus::Rejected, '2026-10-07 08:00:00');

    $summary = runBackfill(['--apply' => true]);

    expect($financeApproved->refresh()->sourcing_completed_at->toDateTimeString())->toBe('2026-10-05 08:00:00')
        ->and($rndRejected->refresh()->sourcing_completed_at->toDateTimeString())->toBe('2026-10-04 08:00:00')
        ->and($sampleApproved->refresh()->sourcing_completed_at->toDateTimeString())->toBe('2026-10-06 08:00:00')
        ->and($sampleRejected->refresh()->sourcing_completed_at->toDateTimeString())->toBe('2026-10-07 08:00:00')
        ->and($summary)->toMatchArray(['scanned' => 4, 'eligible' => 4, 'updated' => 4, 'skipped_ambiguous' => 0]);
});

it('starts standalone sourcing at creation and leaves project records without proof null', function () {
    $standalone = backfillMaterial(['sourcing_origin' => RndProductEsbMaterial::SOURCING_ORIGIN_STANDALONE, 'sourcing_status' => MaterialSourcingStatus::PendingRndReview, 'sourcing_requested_at' => null]);
    $project = backfillMaterial(['sourcing_status' => MaterialSourcingStatus::PendingRndReview, 'sourcing_requested_at' => null, 'updated_at' => '2026-10-10 08:00:00']);

    $summary = runBackfill(['--apply' => true]);

    expect($standalone->refresh()->sourcing_requested_at->toDateTimeString())->toBe('2026-09-01 08:00:00')
        ->and($project->refresh()->sourcing_requested_at)->toBeNull()
        ->and($summary)->toMatchArray(['requested_at_candidates' => 1, 'requested_at_ambiguous' => 1, 'skipped_ambiguous' => 1]);
});

it('keeps existing values and active records, and skips WIP and materials not in sourcing', function () {
    $valid = backfillMaterial(['sourcing_completed_at' => '2026-10-03 08:00:00', 'finance_reviewed_at' => '2026-10-05 08:00:00']);
    $resample = backfillMaterial(['sourcing_status' => MaterialSourcingStatus::ResampleRequested]);
    backfillCycle($resample, MaterialSampleCycleStatus::ResampleRequested, '2026-10-05 08:00:00');
    $wip = RndProductEsbMaterial::factory()->wip()->create(['sourcing_status' => MaterialSourcingStatus::NotRequired]);
    $notStarted = RndProductEsbMaterial::factory()->legacy()->create();

    $summary = runBackfill(['--apply' => true]);

    expect($valid->refresh()->sourcing_completed_at->toDateTimeString())->toBe('2026-10-03 08:00:00')
        ->and($resample->refresh()->sourcing_completed_at)->toBeNull()
        ->and($wip->refresh()->sourcing_requested_at)->toBeNull()
        ->and($notStarted->refresh()->sourcing_requested_at)->toBeNull()
        ->and($summary)->toMatchArray(['scanned' => 2, 'eligible' => 0, 'updated' => 0, 'unchanged' => 2]);
});

it('never guesses a completion', function (array $attributes, ?array $cycle) {
    $material = backfillMaterial($attributes);
    if ($cycle) {
        backfillCycle($material, ...$cycle);
    }

    $summary = runBackfill(['--apply' => true]);

    expect($material->refresh()->sourcing_completed_at)->toBeNull()
        ->and($summary)->toMatchArray(['completed_at_ambiguous' => 1, 'skipped_ambiguous' => 1, 'updated' => 0]);
})->with([
    'no source, updated_at is not one' => [['updated_at' => '2026-10-10 08:00:00'], null],
    'workflow_completed_at is not one' => [['workflow_completed_at' => '2026-10-10 08:00:00'], null],
    'no cycle' => [['sourcing_status' => MaterialSourcingStatus::ApprovedByRnd, 'rnd_reviewed_at' => '2026-10-10 08:00:00'], null],
    'latest cycle has another status' => [['sourcing_status' => MaterialSourcingStatus::ApprovedByRnd], [MaterialSampleCycleStatus::Rejected, '2026-10-10 08:00:00']],
    'matching cycle without evaluation' => [['sourcing_status' => MaterialSourcingStatus::ApprovedByRnd], [MaterialSampleCycleStatus::Approved, null]],
]);

it('does not take a matching cycle when a newer cycle exists', function () {
    $material = backfillMaterial(['sourcing_status' => MaterialSourcingStatus::ApprovedByRnd]);
    backfillCycle($material, MaterialSampleCycleStatus::Approved, '2026-10-05 08:00:00', 1);
    backfillCycle($material, MaterialSampleCycleStatus::Prepared, null, 2);

    runBackfill(['--apply' => true]);

    expect($material->refresh()->sourcing_completed_at)->toBeNull();
});

it('skips a decision reopened afterwards but accepts one made after the reopen', function () {
    $reopenedAfter = backfillMaterial(['finance_reviewed_at' => '2026-10-05 08:00:00']);
    $reopenedBefore = backfillMaterial(['finance_reviewed_at' => '2026-10-05 08:00:00']);
    foreach ([[$reopenedAfter, '2026-10-06 08:00:00'], [$reopenedBefore, '2026-10-04 08:00:00']] as [$material, $at]) {
        $audit = RndMaterialAuditLog::query()->create(['rnd_product_esb_material_id' => $material->id, 'event' => 'sample_approval_reopened', 'metadata' => []]);
        DB::table('rnd_material_audit_logs')->where('id', $audit->id)->update(['created_at' => $at]);
    }

    runBackfill(['--apply' => true]);

    expect($reopenedAfter->refresh()->sourcing_completed_at)->toBeNull()
        ->and($reopenedBefore->refresh()->sourcing_completed_at->toDateTimeString())->toBe('2026-10-05 08:00:00');
});

it('reports invalid chronology without writing it', function () {
    $sourceBeforeStart = backfillMaterial(['finance_reviewed_at' => '2026-09-20 08:00:00']);
    backfillMaterial(['sourcing_completed_at' => '2026-09-20 08:00:00']);

    $summary = runBackfill(['--apply' => true]);

    expect($sourceBeforeStart->refresh()->sourcing_completed_at)->toBeNull()
        ->and($summary)->toMatchArray(['invalid_chronology' => 2, 'updated' => 0]);
});

it('is idempotent, chunked, and leaves every other column alone', function () {
    $materials = collect(range(1, 5))->map(fn (): RndProductEsbMaterial => backfillMaterial([
        'finance_reviewed_at' => '2026-10-05 08:00:00',
        'workflow_completed_at' => '2026-10-20 08:00:00',
        'updated_at' => '2026-10-21 08:00:00',
    ]));
    $auditCount = RndMaterialAuditLog::query()->count();

    expect(runBackfill(['--apply' => true, '--chunk' => 2]))->toMatchArray(['scanned' => 5, 'updated' => 5]);

    $materials->each(function (RndProductEsbMaterial $material): void {
        $material->refresh();
        expect($material->sourcing_completed_at->toDateTimeString())->toBe('2026-10-05 08:00:00')
            ->and($material->updated_at->toDateTimeString())->toBe('2026-10-21 08:00:00')
            ->and($material->workflow_completed_at->toDateTimeString())->toBe('2026-10-20 08:00:00')
            ->and($material->sourcing_status)->toBe(MaterialSourcingStatus::Approved);
    });

    expect(RndMaterialAuditLog::query()->count())->toBe($auditCount)
        ->and(runBackfill())->toMatchArray(['eligible' => 0, 'updated' => 0, 'unchanged' => 5])
        ->and(runBackfill(['--apply' => true]))->toMatchArray(['eligible' => 0, 'updated' => 0, 'unchanged' => 5]);
});

it('prints only aggregate counters and calls nothing external', function () {
    Http::fake();
    $material = backfillMaterial(['product_name' => 'Bahan Rahasia', 'finance_reviewed_at' => '2026-10-05 08:00:00']);

    Artisan::call('rnd:backfill-sourcing-durations', ['--apply' => true]);
    $output = Artisan::output();

    expect($output)->not->toContain('Bahan Rahasia')
        ->not->toContain($material->product_code);
    Http::assertNothingSent();
});

it('does not write a record reopened while the command is running', function () {
    $material = backfillMaterial(['finance_reviewed_at' => '2026-10-05 08:00:00']);
    DB::listen(function ($query) use ($material): void {
        if (str_contains($query->sql, 'rnd_material_sample_cycles')) {
            DB::table('rnd_product_esb_materials')->where('id', $material->id)->update(['sourcing_status' => MaterialSourcingStatus::PendingRndReview->value]);
        }
    });

    $summary = runBackfill(['--apply' => true]);

    expect($material->refresh()->sourcing_completed_at)->toBeNull()
        ->and($summary)->toMatchArray(['eligible' => 1, 'updated' => 0, 'failed' => 0]);
});
