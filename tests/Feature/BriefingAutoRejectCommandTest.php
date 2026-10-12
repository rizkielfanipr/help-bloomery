<?php

use App\Enums\BriefingPeriod;
use App\Enums\BriefingReviewStatus;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use App\Models\BriefingSettings;
use App\Models\BriefingTask;
use App\Models\User;

beforeEach(function () {
    $this->staff = User::factory()->create(['is_active' => true]);

    $this->record = BriefingRecord::create([
        'user_id' => $this->staff->id,
        'period' => BriefingPeriod::Daily->value,
        'record_date' => now()->toDateString(),
        'submitted_at' => now(),
    ]);
});

it('rejects an item past the configured auto-reject window', function () {
    BriefingSettings::instance()->update(['auto_reject_after_days' => 3]);

    $item = BriefingItem::create([
        'briefing_record_id' => $this->record->id,
        'task_key' => 'daily_selfie_pagi',
        'is_completed' => true,
        'completed_at' => now()->subDays(4),
        'review_status' => BriefingReviewStatus::SupervisorReview->value,
    ]);

    $this->artisan('briefing:auto-reject')->assertSuccessful();

    $item->refresh();
    expect($item->review_status)->toBe(BriefingReviewStatus::Rejected)
        ->and($item->rejection_reason)->toBe('Tidak ada approval dalam 3 hari setelah poin diselesaikan.')
        ->and($item->reviewed_by)->toBeNull();
});

it('does not reject an item still within the configured auto-reject window', function () {
    BriefingSettings::instance()->update(['auto_reject_after_days' => 3]);

    $item = BriefingItem::create([
        'briefing_record_id' => $this->record->id,
        'task_key' => 'daily_selfie_pagi',
        'is_completed' => true,
        'completed_at' => now()->subDays(2),
        'review_status' => BriefingReviewStatus::SupervisorReview->value,
    ]);

    $this->artisan('briefing:auto-reject')->assertSuccessful();

    expect($item->refresh()->review_status)->toBe(BriefingReviewStatus::SupervisorReview);
});

it('respects a custom configured window instead of the old hardcoded 7 days', function () {
    BriefingSettings::instance()->update(['auto_reject_after_days' => 1]);

    $item = BriefingItem::create([
        'briefing_record_id' => $this->record->id,
        'task_key' => 'daily_selfie_pagi',
        'is_completed' => true,
        'completed_at' => now()->subDays(2),
        'review_status' => BriefingReviewStatus::SupervisorReview->value,
    ]);

    $this->artisan('briefing:auto-reject')->assertSuccessful();

    expect($item->refresh()->review_status)->toBe(BriefingReviewStatus::Rejected);
});

it('uses the configured auto-reject reason template with the day count filled in', function () {
    BriefingSettings::instance()->update([
        'auto_reject_after_days' => 2,
        'auto_reject_reason' => 'Poin ditolak otomatis karena tidak di-approve dalam :days hari.',
    ]);

    $item = BriefingItem::create([
        'briefing_record_id' => $this->record->id,
        'task_key' => 'daily_selfie_pagi',
        'is_completed' => true,
        'completed_at' => now()->subDays(3),
        'review_status' => BriefingReviewStatus::SupervisorReview->value,
    ]);

    $this->artisan('briefing:auto-reject')->assertSuccessful();

    expect($item->refresh()->rejection_reason)->toBe('Poin ditolak otomatis karena tidak di-approve dalam 2 hari.');
});

it(
    'never creates a record or item for a task that missed its deadline with no submission (contract change: '
    .'missing is derived by BriefingStatusService, not a synthetic rejected row — '
    .'docs/daily-briefing-remediation-prd.md §11.4)',
    function () {
        BriefingTask::create([
            'key' => 'test_missed_deadline_no_submission',
            'label' => 'Test Missed Deadline No Submission',
            'period' => 'daily',
            'submission_type' => 'photo',
            'deadline_enabled' => true,
            'deadline_time' => now()->subMinute()->format('H:i'),
            'sort_order' => 99,
            'is_active' => true,
        ]);

        BriefingTask::clearCache();

        $beforeRecordCount = BriefingRecord::count();

        $this->artisan('briefing:auto-reject')->assertSuccessful();

        expect(BriefingRecord::count())->toBe($beforeRecordCount)
            ->and(BriefingItem::where('task_key', 'test_missed_deadline_no_submission')->exists())->toBeFalse();
    }
);

it('does not touch items already approved or rejected', function () {
    BriefingSettings::instance()->update(['auto_reject_after_days' => 3]);

    $approved = BriefingItem::create([
        'briefing_record_id' => $this->record->id,
        'task_key' => 'daily_selfie_pagi',
        'is_completed' => true,
        'completed_at' => now()->subDays(10),
        'review_status' => BriefingReviewStatus::Approved->value,
        'reviewed_by' => $this->staff->id,
        'reviewed_at' => now()->subDays(9),
    ]);
    $rejected = BriefingItem::create([
        'briefing_record_id' => $this->record->id,
        'task_key' => 'daily_selfie_sore',
        'is_completed' => true,
        'completed_at' => now()->subDays(10),
        'review_status' => BriefingReviewStatus::Rejected->value,
        'rejection_reason' => 'Alasan manual.',
        'reviewed_by' => $this->staff->id,
        'reviewed_at' => now()->subDays(9),
    ]);

    $this->artisan('briefing:auto-reject')->assertSuccessful();

    expect($approved->refresh()->review_status)->toBe(BriefingReviewStatus::Approved)
        ->and($rejected->refresh()->rejection_reason)->toBe('Alasan manual.');
});
