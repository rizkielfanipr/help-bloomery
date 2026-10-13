<?php

use App\Enums\BriefingBundleStatus;
use App\Enums\BriefingPeriod;
use App\Enums\BriefingSubmissionType;
use App\Enums\BriefingTransitionSource;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use App\Models\BriefingRecordRequirement;
use App\Models\BriefingRecordTransition;
use App\Models\BriefingTask;
use App\Models\User;
use App\Services\BriefingPeriodNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * docs/daily-briefing-bundle-approval-prd.md Phase 2: additive bundle schema, its casts and
 * relationships, the period_start normalizer, the bundle state machine, and the requirement
 * snapshot's independence from the task master.
 */
describe('period normalizer', function () {
    it('normalizes a date to its bundle period_start', function (BriefingPeriod $period, string $date, string $expectedStart) {
        $start = app(BriefingPeriodNormalizer::class)->start($period, CarbonImmutable::parse($date.' 14:35:00'));

        expect($start->toDateTimeString())->toBe($expectedStart.' 00:00:00');
    })->with([
        'daily keeps the obligation date' => [BriefingPeriod::Daily, '2026-10-14', '2026-10-14'],
        'weekly Monday is its own start' => [BriefingPeriod::Weekly, '2026-10-12', '2026-10-12'],
        'weekly Wednesday rolls back to Monday' => [BriefingPeriod::Weekly, '2026-10-14', '2026-10-12'],
        'weekly Sunday belongs to the week that began on Monday' => [BriefingPeriod::Weekly, '2026-10-18', '2026-10-12'],
        'weekly crossing a month boundary' => [BriefingPeriod::Weekly, '2026-11-01', '2026-10-26'],
        'monthly rolls back to the first' => [BriefingPeriod::Monthly, '2026-10-31', '2026-10-01'],
    ]);

    it('normalizes a date to its bundle period_end', function (BriefingPeriod $period, string $date, string $expectedEnd) {
        $end = app(BriefingPeriodNormalizer::class)->end($period, CarbonImmutable::parse($date));

        expect($end->toDateString())->toBe($expectedEnd);
    })->with([
        'daily ends the same day' => [BriefingPeriod::Daily, '2026-10-14', '2026-10-14'],
        'weekly ends on Sunday' => [BriefingPeriod::Weekly, '2026-10-14', '2026-10-18'],
        'monthly ends on the last day of a leap February' => [BriefingPeriod::Monthly, '2028-02-10', '2028-02-29'],
    ]);

    it('pins the business week to Monday regardless of the Carbon locale', function () {
        CarbonImmutable::setLocale('en_US');

        $start = app(BriefingPeriodNormalizer::class)->start(BriefingPeriod::Weekly, CarbonImmutable::parse('2026-10-18'));

        expect($start->isMonday())->toBeTrue();
    });
});

describe('bundle state machine', function () {
    it('allows only the PRD transitions', function (BriefingBundleStatus $from, BriefingBundleStatus $to, bool $allowed) {
        expect($from->canTransitionTo($to))->toBe($allowed);
    })->with([
        'draft becomes reviewable once complete' => [BriefingBundleStatus::Draft, BriefingBundleStatus::SupervisorReview, true],
        'draft cannot be approved' => [BriefingBundleStatus::Draft, BriefingBundleStatus::Approved, false],
        'draft cannot be rejected' => [BriefingBundleStatus::Draft, BriefingBundleStatus::Rejected, false],
        'review can be approved' => [BriefingBundleStatus::SupervisorReview, BriefingBundleStatus::Approved, true],
        'review can be rejected' => [BriefingBundleStatus::SupervisorReview, BriefingBundleStatus::Rejected, true],
        'approved is final until reopened' => [BriefingBundleStatus::Approved, BriefingBundleStatus::SupervisorReview, false],
        'approved can be reopened' => [BriefingBundleStatus::Approved, BriefingBundleStatus::Reopened, true],
        'rejected needs a reopen before review again' => [BriefingBundleStatus::Rejected, BriefingBundleStatus::SupervisorReview, false],
        'rejected can be reopened' => [BriefingBundleStatus::Rejected, BriefingBundleStatus::Reopened, true],
        'reopened returns to review when complete again' => [BriefingBundleStatus::Reopened, BriefingBundleStatus::SupervisorReview, true],
        'reopened cannot be approved directly' => [BriefingBundleStatus::Reopened, BriefingBundleStatus::Approved, false],
        'a status never transitions to itself' => [BriefingBundleStatus::Approved, BriefingBundleStatus::Approved, false],
    ]);

    it('only accepts new submissions while draft or reopened', function () {
        $accepting = collect(BriefingBundleStatus::cases())
            ->filter(fn (BriefingBundleStatus $status): bool => $status->acceptsSubmissions())
            ->values()
            ->all();

        expect($accepting)->toBe([BriefingBundleStatus::Draft, BriefingBundleStatus::Reopened]);
    });
});

describe('bundle casts and relationships', function () {
    it('casts the bundle columns and resolves reviewer, requirements, and transitions', function () {
        $reviewer = User::factory()->create();
        $record = BriefingRecord::factory()->create([
            'period_start' => '2026-10-12',
            'period_end' => '2026-10-18',
            'status' => BriefingBundleStatus::Approved->value,
            'completed_at' => '2026-10-13 10:00:00',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => '2026-10-13 11:00:00',
            'scoring_method_version' => 3,
        ]);
        BriefingRecordRequirement::factory()->for($record, 'record')->create();
        BriefingRecordTransition::factory()->for($record, 'record')->create(['actor_id' => $reviewer->id]);

        $record->refresh();

        expect($record->status)->toBe(BriefingBundleStatus::Approved)
            ->and($record->period_start->toDateString())->toBe('2026-10-12')
            ->and($record->period_end->toDateString())->toBe('2026-10-18')
            ->and($record->scoring_method_version)->toBe(3)
            ->and($record->isLegacyUngrouped())->toBeFalse()
            ->and($record->reviewer->is($reviewer))->toBeTrue()
            ->and($record->requirements)->toHaveCount(1)
            ->and($record->transitions->sole()->source)->toBe(BriefingTransitionSource::UserAction)
            ->and($record->transitions->sole()->to_status)->toBe(BriefingBundleStatus::SupervisorReview)
            ->and($record->transitions->sole()->actor->is($reviewer))->toBeTrue();
    });

    it('treats a row without a bundle status as legacy and ungrouped', function () {
        expect(BriefingRecord::factory()->create()->isLegacyUngrouped())->toBeTrue();
    });

    it('records the contributor and correction flag on an item', function () {
        $contributor = User::factory()->create();
        $item = BriefingItem::factory()->create([
            'submitted_by' => $contributor->id,
            'submitted_at' => '2026-10-13 09:00:00',
        ]);

        $item->refresh();

        expect($item->submitter->is($contributor))->toBeTrue()
            ->and($item->submitted_at->toDateTimeString())->toBe('2026-10-13 09:00:00')
            ->and($item->requires_correction)->toBeFalse()
            ->and((new BriefingItem)->requires_correction)->toBeFalse();
    });
});

describe('requirement snapshot', function () {
    it('keeps the snapshotted obligation unchanged after the task master is edited', function () {
        $task = BriefingTask::create([
            'key' => 'snapshot_test_task',
            'label' => 'Label Asli',
            'period' => 'daily',
            'submission_type' => 'camera_only',
            'is_active' => true,
            'include_in_score' => true,
            'sort_order' => 4,
        ]);
        $record = BriefingRecord::factory()->create();

        $requirement = $record->requirements()->create(BriefingRecordRequirement::attributesFromTask($task));

        $task->update(['label' => 'Label Baru', 'submission_type' => 'text_only', 'include_in_score' => false]);

        $requirement->refresh();

        expect($requirement->label)->toBe('Label Asli')
            ->and($requirement->submission_type)->toBe(BriefingSubmissionType::CameraOnly)
            ->and($requirement->include_in_score)->toBeTrue()
            ->and($requirement->is_required)->toBeTrue()
            ->and($requirement->sort_order)->toBe(4);
    });

    it('allows a task key only once per bundle', function () {
        $record = BriefingRecord::factory()->create();
        BriefingRecordRequirement::factory()->for($record, 'record')->create(['task_key' => 'duplicate_key']);

        expect(fn () => BriefingRecordRequirement::factory()->for($record, 'record')->create(['task_key' => 'duplicate_key']))
            ->toThrow(UniqueConstraintViolationException::class);
    });
});
