<?php

use App\Enums\MaterialSourcingStatus;
use App\Models\RndProductEsbMaterial;
use App\Services\Rnd\Material\MaterialSourcingTimeline;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class);

function timelineMaterial(MaterialSourcingStatus $status, ?string $startedAt, ?string $completedAt = null): RndProductEsbMaterial
{
    return (new RndProductEsbMaterial)->forceFill([
        'sourcing_status' => $status,
        'sourcing_requested_at' => $startedAt,
        'sourcing_completed_at' => $completedAt,
    ]);
}

it('formats a calendar span without rounding it up', function (int $seconds, ?string $expected) {
    expect(MaterialSourcingTimeline::formatSeconds($seconds))->toBe($expected);
})->with([
    'under a minute' => [59, 'Kurang dari 1 menit'],
    'minutes' => [59 * 60 + 59, '59 menit'],
    'exactly one hour' => [3600, '1 jam'],
    'hours' => [23 * 3600 + 3599, '23 jam'],
    'exactly one day' => [86400, '1 hari'],
    'days and hours' => [6 * 86400 + 6 * 3600, '6 hari 6 jam'],
    'invalid chronology' => [-1, null],
]);

it('shows a running duration with its suffix up to the reference time', function () {
    $material = timelineMaterial(MaterialSourcingStatus::ResampleRequested, '2026-11-01 08:00:00');

    expect(MaterialSourcingTimeline::durationLabel($material, Carbon::parse('2026-11-05 10:00:00')))->toBe('4 hari 2 jam berjalan')
        ->and(MaterialSourcingTimeline::startedLabel($material))->toBe('01 Nov 2026 08:00')
        ->and(MaterialSourcingTimeline::completedLabel($material))->toBe(MaterialSourcingTimeline::NOT_APPLICABLE);
});

it('freezes the duration once sourcing is final', function () {
    $material = timelineMaterial(MaterialSourcingStatus::ApprovedByRnd, '2026-11-01 08:00:00', '2026-11-07 14:00:00');

    expect(MaterialSourcingTimeline::durationLabel($material, Carbon::parse('2027-01-01 00:00:00')))->toBe('6 hari 6 jam')
        ->and(MaterialSourcingTimeline::completedLabel($material))->toBe('07 Nov 2026 14:00');
});

it('never shows a number for ambiguous data', function (RndProductEsbMaterial $material) {
    expect(MaterialSourcingTimeline::durationLabel($material, Carbon::parse('2026-11-05 10:00:00')))->toBe(MaterialSourcingTimeline::NOT_AVAILABLE);
})->with([
    'no start' => fn () => timelineMaterial(MaterialSourcingStatus::PendingRndReview, null),
    'final without start' => fn () => timelineMaterial(MaterialSourcingStatus::Approved, null, '2026-11-01 08:00:00'),
    'final without completion' => fn () => timelineMaterial(MaterialSourcingStatus::Rejected, '2026-11-01 08:00:00'),
    'completion before start' => fn () => timelineMaterial(MaterialSourcingStatus::ApprovedByRnd, '2026-11-02 08:00:00', '2026-11-01 08:00:00'),
    'start in the future' => fn () => timelineMaterial(MaterialSourcingStatus::WaitingPurchasing, '2026-11-06 08:00:00'),
]);

it('labels a final record without proof as not available and an untracked record as not applicable', function () {
    $ambiguous = timelineMaterial(MaterialSourcingStatus::Approved, null);
    $wip = timelineMaterial(MaterialSourcingStatus::NotRequired, null);

    expect(MaterialSourcingTimeline::startedLabel($ambiguous))->toBe(MaterialSourcingTimeline::NOT_AVAILABLE)
        ->and(MaterialSourcingTimeline::completedLabel($ambiguous))->toBe(MaterialSourcingTimeline::NOT_AVAILABLE)
        ->and(MaterialSourcingTimeline::startedLabel($wip))->toBe(MaterialSourcingTimeline::NOT_APPLICABLE)
        ->and(MaterialSourcingTimeline::completedLabel($wip))->toBe(MaterialSourcingTimeline::NOT_APPLICABLE)
        ->and(MaterialSourcingTimeline::durationLabel($wip))->toBe(MaterialSourcingTimeline::NOT_APPLICABLE);
});

it('runs no query while presenting a record', function () {
    $material = timelineMaterial(MaterialSourcingStatus::ApprovedByRnd, '2026-11-01 08:00:00', '2026-11-07 14:00:00');
    DB::enableQueryLog();

    MaterialSourcingTimeline::startedLabel($material);
    MaterialSourcingTimeline::completedLabel($material);
    MaterialSourcingTimeline::durationLabel($material);

    expect(DB::getQueryLog())->toBeEmpty();
});
