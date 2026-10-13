<?php

namespace App\Actions\Briefing;

use App\Enums\BriefingBundleStatus;
use App\Enums\BriefingReviewStatus;
use App\Enums\BriefingTransitionSource;
use App\Exceptions\Briefing\BriefingBundleConflictException;
use App\Exceptions\Briefing\BriefingBundleUnavailableException;
use App\Models\Branch;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use App\Models\BriefingTask;
use App\Models\User;
use App\Services\BriefingAssignmentService;
use App\Services\BriefingBundleCompletenessService;
use App\Services\BriefingBundleResolver;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * docs/daily-briefing-bundle-approval-prd.md §7.3/§8/§17 (Phase 4). The only path that creates or
 * updates a briefing submission. A Livewire task key is untrusted input, so permission,
 * participation and assignment are re-checked here; the point is saved into the branch's one
 * canonical bundle with its contributor, and the bundle moves to supervisor_review only once every
 * required point in its snapshot is valid.
 */
class BriefingSubmissionAction
{
    public function __construct(
        private readonly BriefingAssignmentService $assignments,
        private readonly BriefingBundleResolver $bundles,
        private readonly BriefingBundleCompletenessService $completeness,
    ) {}

    /**
     * @param  array{notes?: ?string, photo_paths?: string[]}  $payload
     */
    public function execute(User $actor, BriefingTask $task, array $payload, CarbonInterface $obligationDate): BriefingSubmissionResult
    {
        if (! $this->assignments->canSubmit($actor, $obligationDate)) {
            return BriefingSubmissionResult::denied(
                'not_participant',
                'Anda tidak terdaftar sebagai peserta Daily Briefing.',
            );
        }

        if (! $this->assignments->taskIsAssignedTo($actor, $task, $obligationDate)) {
            return BriefingSubmissionResult::denied(
                'task_not_assigned',
                'Tugas ini tidak tersedia untuk cabang Anda.',
            );
        }

        try {
            $existing = $this->bundles->find((int) $actor->branch_id, $task->period, $obligationDate)?->load(['requirements', 'items']);
        } catch (BriefingBundleConflictException $exception) {
            return BriefingSubmissionResult::denied('bundle_conflict', $exception->getMessage());
        }

        // Rejected before anything is created, so a failed attempt never leaves an empty bundle behind.
        if ($denial = $this->denialFor($existing, $task, $payload)) {
            return $denial;
        }

        try {
            $bundle = $existing ?? $this->bundles->findOrCreate(Branch::findOrFail($actor->branch_id), $task->period, $obligationDate, $actor);
        } catch (BriefingBundleUnavailableException $exception) {
            return BriefingSubmissionResult::denied($exception->reason, $exception->getMessage());
        } catch (BriefingBundleConflictException $exception) {
            return BriefingSubmissionResult::denied('bundle_conflict', $exception->getMessage());
        }

        return DB::transaction(function () use ($actor, $task, $payload, $bundle): BriefingSubmissionResult {
            $bundle = BriefingRecord::query()->whereKey($bundle->getKey())->lockForUpdate()->firstOrFail();
            $bundle->load(['requirements', 'items']);

            // Re-checked on the locked row: another contributor or a reviewer may have moved the bundle on.
            if ($denial = $this->denialFor($bundle, $task, $payload)) {
                return $denial;
            }

            $item = $bundle->items->firstWhere('task_key', $task->key)
                ?? $bundle->items()->make(['task_key' => $task->key]);
            $photoPaths = $payload['photo_paths'] ?? [];
            $notes = $payload['notes'] ?? null;

            $consumesLegacyReopenGrant = $item->exists && $item->hasOpenReopenGrant();

            $item->fill([
                'photo_paths' => ! empty($photoPaths) ? $photoPaths : ($item->photo_paths ?? []),
                'notes' => $notes,
                'is_completed' => true,
                'completed_at' => now(),
                'submitted_by' => $actor->id,
                'submitted_at' => now(),
                'requires_correction' => false,
                'review_status' => BriefingReviewStatus::SupervisorReview->value,
                'rejection_reason' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]);

            if ($consumesLegacyReopenGrant) {
                $item->reopened_at = null;
            }

            $item->save();

            if (! $bundle->submitted_at) {
                $bundle->submitted_at = now();
            }

            $this->advanceWhenComplete($bundle->load('items'), $actor);

            return BriefingSubmissionResult::success($item);
        });
    }

    /**
     * §8/§9: only draft and reopened bundles take submissions; a not-yet-created bundle behaves as a
     * fresh draft judged by the task it would snapshot. In a reopened bundle the supervisor's
     * correction flags, when present, limit which points may be touched. Evidence is judged against
     * the bundle's snapshotted submission type, not the live task.
     *
     * @param  array{notes?: ?string, photo_paths?: string[]}  $payload
     */
    private function denialFor(?BriefingRecord $bundle, BriefingTask $task, array $payload): ?BriefingSubmissionResult
    {
        $requirement = $bundle?->requirements->firstWhere('task_key', $task->key);

        if ($bundle !== null && $requirement === null) {
            return BriefingSubmissionResult::denied(
                'task_not_assigned',
                'Tugas ini tidak termasuk kewajiban periode ini.',
            );
        }

        $item = $bundle?->items->firstWhere('task_key', $task->key);

        if ($bundle !== null && ($denial = $this->stateDenial($bundle, $item, $task))) {
            return $denial;
        }

        if ($bundle === null && $task->isPastDeadline()) {
            return BriefingSubmissionResult::denied(
                'deadline_passed',
                'Waktu pengisian sudah berakhir. Tugas ini tidak dapat disubmit.',
            );
        }

        $submissionType = $requirement?->submission_type ?? $task->submission_type;

        if ($submissionType->requiresPhoto() && empty($payload['photo_paths'] ?? []) && empty($item?->photo_paths)) {
            return BriefingSubmissionResult::denied(
                'photo_required',
                'Harap ambil foto terlebih dahulu sebagai bukti.',
            );
        }

        if ($submissionType->requiresText() && blank($payload['notes'] ?? null)) {
            return BriefingSubmissionResult::denied(
                'notes_required',
                'Harap isi catatan terlebih dahulu.',
            );
        }

        return null;
    }

    private function stateDenial(BriefingRecord $bundle, ?BriefingItem $item, BriefingTask $task): ?BriefingSubmissionResult
    {
        if (! $bundle->status->acceptsSubmissions()) {
            return BriefingSubmissionResult::denied(...match ($bundle->status) {
                BriefingBundleStatus::SupervisorReview => ['bundle_in_review', 'Semua poin sudah lengkap dan sedang menunggu persetujuan supervisor.'],
                BriefingBundleStatus::Approved => ['bundle_approved', 'Briefing periode ini sudah disetujui dan tidak dapat diubah lagi.'],
                default => ['bundle_rejected', 'Briefing periode ini ditolak. Minta supervisor membuka kembali agar poin dapat diperbaiki.'],
            });
        }

        if ($item?->review_status === BriefingReviewStatus::Approved) {
            return BriefingSubmissionResult::denied(
                'already_approved',
                'Tugas ini sudah disetujui dan tidak dapat diubah lagi.',
            );
        }

        $isReopened = $bundle->status === BriefingBundleStatus::Reopened;

        if ($isReopened && $bundle->items->contains('requires_correction', true) && ! $item?->requires_correction) {
            return BriefingSubmissionResult::denied(
                'not_flagged_for_correction',
                'Hanya poin yang ditandai supervisor yang dapat diperbaiki.',
            );
        }

        if ($task->isPastDeadline() && ! $isReopened && ! $item?->hasOpenReopenGrant()) {
            return BriefingSubmissionResult::denied(
                'deadline_passed',
                'Waktu pengisian sudah berakhir. Tugas ini tidak dapat disubmit.',
            );
        }

        return null;
    }

    private function advanceWhenComplete(BriefingRecord $bundle, User $actor): void
    {
        if (! $this->completeness->evaluate($bundle)->isComplete()) {
            $bundle->save();

            return;
        }

        $from = $bundle->status;

        $bundle->fill([
            'status' => BriefingBundleStatus::SupervisorReview->value,
            'completed_at' => $bundle->completed_at ?? now(),
        ])->save();

        $bundle->transitions()->create([
            'from_status' => $from->value,
            'to_status' => BriefingBundleStatus::SupervisorReview->value,
            'actor_id' => $actor->id,
            'source' => BriefingTransitionSource::UserAction->value,
        ]);
    }
}
