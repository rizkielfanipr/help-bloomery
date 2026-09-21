<?php

use App\Enums\BriefingPeriod;
use App\Enums\BriefingReviewStatus;
use App\Models\Branch;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use App\Models\User;

function briefingItem(BriefingRecord $record, string $taskKey, BriefingReviewStatus $status, array $attributes = []): BriefingItem
{
    return BriefingItem::create([
        'briefing_record_id' => $record->id,
        'task_key' => $taskKey,
        'is_completed' => true,
        'completed_at' => now()->subDays(5),
        'review_status' => $status->value,
        'rejection_reason' => $status === BriefingReviewStatus::Rejected ? 'Ditolak' : null,
        ...$attributes,
    ]);
}

beforeEach(function () {
    $this->jateng = Branch::factory()->create(['name' => 'Kitchen Jateng']);
    $this->jakarta = Branch::factory()->create(['name' => 'Kitchen Jakarta']);
    $this->other = Branch::factory()->create(['name' => 'Store Bandung']);
    $this->reviewer = User::factory()->create();

    $this->recordFor = fn (Branch $branch, string $date = 'today'): BriefingRecord => BriefingRecord::create([
        'user_id' => User::factory()->create(['branch_id' => $branch->id])->id,
        'branch_id' => $branch->id,
        'period' => BriefingPeriod::Daily->value,
        'record_date' => now()->parse($date)->toDateString(),
        'submitted_at' => now(),
    ]);
});

it('approves pending and system-rejected submissions of the given branches only', function () {
    $jatengRecord = ($this->recordFor)($this->jateng);
    $jakartaRecord = ($this->recordFor)($this->jakarta);
    $otherRecord = ($this->recordFor)($this->other);

    $pending = briefingItem($jatengRecord, 'task_pending', BriefingReviewStatus::SupervisorReview);
    $legacyPending = briefingItem($jakartaRecord, 'task_legacy', BriefingReviewStatus::LegacyPending);
    $systemRejected = briefingItem($jatengRecord, 'task_expired', BriefingReviewStatus::Rejected, ['reviewed_by' => null]);

    $missedDeadline = briefingItem($jatengRecord, 'task_missed', BriefingReviewStatus::Rejected, ['is_completed' => false, 'completed_at' => null]);
    $manualReject = briefingItem($jatengRecord, 'task_manual', BriefingReviewStatus::Rejected, ['reviewed_by' => $this->reviewer->id]);
    $alreadyApproved = briefingItem($jatengRecord, 'task_approved', BriefingReviewStatus::Approved, ['reviewed_by' => $this->reviewer->id]);
    $otherBranch = briefingItem($otherRecord, 'task_other', BriefingReviewStatus::SupervisorReview);

    $this->artisan('briefing:approve-existing', ['branches' => ['Kitchen Jateng', 'kitchen jakarta']])
        ->expectsConfirmation('Approve 3 item briefing di 2 cabang?', 'yes')
        ->assertSuccessful();

    foreach ([$pending, $legacyPending, $systemRejected] as $item) {
        expect($item->fresh())
            ->review_status->toBe(BriefingReviewStatus::Approved)
            ->rejection_reason->toBeNull()
            ->reviewed_at->not->toBeNull();
    }

    expect($missedDeadline->fresh()->review_status)->toBe(BriefingReviewStatus::Rejected)
        ->and($manualReject->fresh()->review_status)->toBe(BriefingReviewStatus::Rejected)
        ->and($manualReject->fresh()->reviewed_by)->toBe($this->reviewer->id)
        ->and($alreadyApproved->fresh()->reviewed_by)->toBe($this->reviewer->id)
        ->and($otherBranch->fresh()->review_status)->toBe(BriefingReviewStatus::SupervisorReview);
});

it('also covers legacy records that only know the branch through their user', function () {
    $record = ($this->recordFor)($this->jateng);
    BriefingRecord::query()->whereKey($record->id)->update(['branch_id' => null]);
    $item = briefingItem($record, 'task_legacy_record', BriefingReviewStatus::SupervisorReview);

    $this->artisan('briefing:approve-existing', ['branches' => ['Kitchen Jateng']])
        ->expectsConfirmation('Approve 1 item briefing di 1 cabang?', 'yes')
        ->assertSuccessful();

    expect($item->fresh()->review_status)->toBe(BriefingReviewStatus::Approved);
});

it('changes nothing on a dry run or when the confirmation is declined', function () {
    $item = briefingItem(($this->recordFor)($this->jateng), 'task_pending', BriefingReviewStatus::SupervisorReview);

    $this->artisan('briefing:approve-existing', ['branches' => ['Kitchen Jateng'], '--dry-run' => true])
        ->expectsOutputToContain('Dry run: 1 item akan di-approve')
        ->assertSuccessful();

    $this->artisan('briefing:approve-existing', ['branches' => ['Kitchen Jateng']])
        ->expectsConfirmation('Approve 1 item briefing di 1 cabang?', 'no')
        ->assertSuccessful();

    expect($item->fresh()->review_status)->toBe(BriefingReviewStatus::SupervisorReview);
});

it('fails without touching data when a branch name is unknown', function () {
    $item = briefingItem(($this->recordFor)($this->jateng), 'task_pending', BriefingReviewStatus::SupervisorReview);

    $this->artisan('briefing:approve-existing', ['branches' => ['Kitchen Jateng', 'Kitchen Surabaya']])
        ->expectsOutputToContain('Cabang tidak ditemukan: kitchen surabaya')
        ->assertFailed();

    expect($item->fresh()->review_status)->toBe(BriefingReviewStatus::SupervisorReview);
});
