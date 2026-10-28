<?php

use App\Enums\BriefingReviewStatus;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use App\Models\User;
use App\Services\BriefingCleanupClassifier;

/**
 * docs/products/daily-briefing/remediation/prd.md §17 (Phase 12). Both commands are dry-run/read-only
 * only in this release — there is no mutating code path at all yet.
 */
function makeRejectedItem(array $overrides = []): BriefingItem
{
    $user = User::factory()->create(['is_active' => true]);

    $record = BriefingRecord::create([
        'user_id' => $user->id,
        'period' => 'daily',
        'record_date' => now()->toDateString(),
    ]);

    return BriefingItem::create(array_merge([
        'briefing_record_id' => $record->id,
        'task_key' => 'daily_selfie_pagi',
        'review_status' => BriefingReviewStatus::Rejected->value,
        'is_completed' => false,
        'completed_at' => null,
        'photo_paths' => null,
        'notes' => null,
        'reviewed_by' => null,
        'reviewed_at' => now(),
        'rejection_reason' => 'Tidak ada input sebelum deadline.',
    ], $overrides));
}

it('audit command never mutates data', function () {
    makeRejectedItem();
    $before = BriefingItem::all()->map->toArray();

    $this->artisan('briefing:audit-invalid-records')->assertSuccessful();

    expect(BriefingItem::all()->map->toArray()->toArray())->toBe($before->toArray());
});

it('cleanup command refuses to run without --dry-run and does not mutate anything', function () {
    makeRejectedItem();
    $beforeCount = BriefingItem::count();

    $this->artisan('briefing:cleanup-invalid-records')->assertFailed();

    expect(BriefingItem::count())->toBe($beforeCount);
});

it('cleanup --dry-run never mutates data', function () {
    makeRejectedItem();
    $before = BriefingItem::all()->map->toArray();

    $this->artisan('briefing:cleanup-invalid-records', ['--dry-run' => true])->assertSuccessful();

    expect(BriefingItem::all()->map->toArray()->toArray())->toBe($before->toArray());
});

it('classifies a never-filled-in, system-rejected item as a safe cleanup candidate', function () {
    $item = makeRejectedItem();

    $classification = app(BriefingCleanupClassifier::class)->classify($item);

    expect($classification)->toBe(BriefingCleanupClassifier::SAFE_CLEANUP_CANDIDATE);
});

it('never classifies a manually-rejected real submission as a synthetic cleanup candidate', function () {
    $staff = User::factory()->create(['is_active' => true]);
    $reviewer = User::factory()->create(['is_active' => true]);

    $item = makeRejectedItem([
        'is_completed' => true,
        'completed_at' => now(),
        'photo_paths' => ['briefing-photos/real.jpg'],
        'reviewed_by' => $reviewer->id,
        'rejection_reason' => 'Foto tidak jelas, harap ulangi.',
    ]);

    $classification = app(BriefingCleanupClassifier::class)->classify($item);

    expect($classification)->toBe(BriefingCleanupClassifier::VALID_SUBMISSION);
});

it('classifies a real submission auto-rejected by the review SLA as manual_review, not safe to clean up', function () {
    $item = makeRejectedItem([
        'is_completed' => true,
        'completed_at' => now()->subDays(5),
        'photo_paths' => ['briefing-photos/real.jpg'],
        'reviewed_by' => null,
    ]);

    $classification = app(BriefingCleanupClassifier::class)->classify($item);

    expect($classification)->toBe(BriefingCleanupClassifier::MANUAL_REVIEW);
});

it('never classifies an approved or pending item as a cleanup candidate', function () {
    $approved = makeRejectedItem(['review_status' => BriefingReviewStatus::Approved->value]);
    $pending = makeRejectedItem(['review_status' => BriefingReviewStatus::SupervisorReview->value]);

    $classifier = app(BriefingCleanupClassifier::class);

    expect($classifier->classify($approved))->toBe(BriefingCleanupClassifier::VALID_SUBMISSION)
        ->and($classifier->classify($pending))->toBe(BriefingCleanupClassifier::VALID_SUBMISSION);
});
