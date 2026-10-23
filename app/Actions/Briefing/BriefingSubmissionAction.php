<?php

namespace App\Actions\Briefing;

use App\Enums\BriefingReviewStatus;
use App\Enums\BriefingSubmissionType;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use App\Models\BriefingTask;
use App\Models\User;
use App\Services\BriefingAssignmentService;
use App\Services\BriefingBackdateEligibility;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * docs/daily-briefing-remediation-prd.md §20 (Phase 5). The only path that creates/updates a
 * briefing submission: validates participation, assignment, deadline, and submission-type rules
 * server-side (a Livewire task key is untrusted input), then persists under the single canonical
 * record for the branch/period/date (§4: unit kewajiban = per branch).
 */
class BriefingSubmissionAction
{
    public function __construct(
        private readonly BriefingAssignmentService $assignments,
        private readonly BriefingBackdateEligibility $backdateEligibility,
    ) {}

    /**
     * @param  array{notes?: ?string, photo_paths?: string[]}  $payload
     */
    public function execute(User $actor, BriefingTask $task, array $payload, CarbonInterface $recordDate): BriefingSubmissionResult
    {
        $recordDate = Carbon::parse($recordDate->toDateString(), config('app.timezone'))->startOfDay();
        $today = now(config('app.timezone'))->startOfDay();

        if ($recordDate->isAfter($today)) {
            return BriefingSubmissionResult::denied(
                'future_date',
                'Tanggal Daily Briefing tidak boleh berada di masa mendatang.',
            );
        }

        $isBackdated = $recordDate->isBefore($today);

        if ($isBackdated && ($reason = $this->backdateEligibility->denialReason($actor, $recordDate))) {
            return BriefingSubmissionResult::denied('backdate_not_allowed', $reason);
        }

        if (! $isBackdated && ! $this->assignments->canSubmit($actor, $recordDate)) {
            return BriefingSubmissionResult::denied(
                'not_participant',
                'Anda tidak terdaftar sebagai peserta Daily Briefing.',
            );
        }

        if (! $this->assignments->taskIsAssignedTo($actor, $task, $recordDate)) {
            return BriefingSubmissionResult::denied(
                'task_not_assigned',
                'Tugas ini tidak tersedia untuk cabang Anda.',
            );
        }

        $branchId = (int) $actor->branch_id;
        $period = $task->period;
        $periodStartDate = BriefingRecord::periodStartDate($period, $recordDate);
        $lockKey = "briefing-submission:{$branchId}:{$period->value}:{$periodStartDate->toDateString()}";

        $lock = Cache::lock($lockKey, 10);

        return $lock->block(5, function () use ($actor, $task, $payload, $recordDate, $branchId, $period, $periodStartDate, $isBackdated): BriefingSubmissionResult {
            return DB::transaction(function () use ($actor, $task, $payload, $recordDate, $branchId, $period, $periodStartDate, $isBackdated): BriefingSubmissionResult {
                $record = BriefingRecord::canonicalForBranch($branchId, $period, $recordDate);
                $item = BriefingRecord::itemsForBranchPeriod($branchId, $period, $recordDate)->get($task->key)
                    ?? $record?->items()->firstOrNew(['task_key' => $task->key])
                    ?? new BriefingItem(['task_key' => $task->key]);

                if ($item->exists) {
                    $record = $item->record;
                }

                if ($item->exists && $item->review_status === BriefingReviewStatus::Approved) {
                    return BriefingSubmissionResult::denied(
                        'already_approved',
                        'Tugas ini sudah disetujui dan tidak dapat diubah lagi.',
                    );
                }

                if ($isBackdated && $item->exists && $this->itemHasSubmission($item)) {
                    return BriefingSubmissionResult::denied(
                        'already_submitted',
                        'Poin ini sudah memiliki input pada periode yang dipilih.',
                    );
                }

                $hasReopenGrant = $item->exists && $item->hasOpenReopenGrant();

                if (! $isBackdated && $task->isPastDeadline($recordDate) && ! $hasReopenGrant) {
                    return BriefingSubmissionResult::denied(
                        'deadline_passed',
                        'Waktu pengisian sudah berakhir. Tugas ini tidak dapat disubmit.',
                    );
                }

                $submissionType = $task->submission_type;
                $photoPaths = $payload['photo_paths'] ?? [];
                $notes = $payload['notes'] ?? null;

                if ($submissionType->requiresPhoto() && empty($photoPaths) && empty($item->photo_paths)) {
                    return BriefingSubmissionResult::denied(
                        'photo_required',
                        'Harap ambil foto terlebih dahulu sebagai bukti.',
                    );
                }

                if ($submissionType->requiresText() && empty(trim($notes ?? ''))) {
                    return BriefingSubmissionResult::denied(
                        'notes_required',
                        'Harap isi catatan terlebih dahulu.',
                    );
                }

                $record ??= BriefingRecord::create([
                    'user_id' => $actor->id,
                    'branch_id' => $branchId,
                    'period' => $period->value,
                    'record_date' => $periodStartDate->toDateString(),
                ]);

                if (! $item->exists) {
                    $item = $record->items()->firstOrNew(['task_key' => $task->key]);
                }

                $isAutoComplete = $submissionType !== BriefingSubmissionType::Checkbox;

                $item->fill([
                    'photo_paths' => ! empty($photoPaths) ? $photoPaths : ($item->photo_paths ?? []),
                    'notes' => $notes,
                    'is_completed' => $isAutoComplete,
                    'completed_at' => $isAutoComplete ? now() : null,
                    'review_status' => BriefingReviewStatus::SupervisorReview->value,
                    'rejection_reason' => null,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ]);

                if ($hasReopenGrant) {
                    $item->reopened_at = null;
                }

                $item->save();

                if (! $record->submitted_at) {
                    $record->update(['submitted_at' => now()]);
                }

                return BriefingSubmissionResult::success($item);
            });
        });
    }

    private function itemHasSubmission(BriefingItem $item): bool
    {
        return in_array($item->review_status, [
            BriefingReviewStatus::LegacyPending,
            BriefingReviewStatus::SupervisorReview,
            BriefingReviewStatus::Approved,
        ], true)
            || $item->is_completed
            || $item->completed_at !== null
            || ! empty($item->photo_paths)
            || filled($item->notes);
    }
}
