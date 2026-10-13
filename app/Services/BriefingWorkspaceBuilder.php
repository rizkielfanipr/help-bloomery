<?php

namespace App\Services;

use App\Enums\BriefingBundleStatus;
use App\Enums\BriefingPeriod;
use App\Enums\BriefingReviewStatus;
use App\Enums\BriefingSubmissionType;
use App\Exceptions\Briefing\BriefingBundleConflictException;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use App\Models\BriefingRecordRequirement;
use App\Models\BriefingTask;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * docs/daily-briefing-bundle-approval-prd.md §12: the Employee App workspace for one staff member —
 * per period, the branch bundle's obligations, progress, status, and which points can still be
 * edited. Mirrors the server-side rules in BriefingSubmissionAction so the UI never offers an edit
 * the action would refuse; the action stays the authority.
 */
class BriefingWorkspaceBuilder
{
    private const ID_MONTHS = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    private const ID_DAYS = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    public function __construct(
        private readonly BriefingAssignmentService $assignments,
        private readonly BriefingBundleResolver $bundles,
        private readonly BriefingBundleCompletenessService $completeness,
        private readonly BriefingPeriodNormalizer $periods,
    ) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public function forUser(User $user, CarbonInterface $date): array
    {
        $liveTasks = BriefingTask::cached()->keyBy('key');
        $workspace = [];

        foreach (BriefingPeriod::cases() as $period) {
            $workspace[$period->value] = $this->period($user, $period, $date, $liveTasks);
        }

        return $workspace;
    }

    /**
     * @param  Collection<string, BriefingTask>  $liveTasks
     * @return array<string, mixed>
     */
    private function period(User $user, BriefingPeriod $period, CarbonInterface $date, Collection $liveTasks): array
    {
        $assigned = $this->assignments->tasksFor($user, $period, $date);
        $conflict = false;
        $bundle = null;

        if ($assigned->isNotEmpty()) {
            try {
                $bundle = $this->bundles->find((int) $user->branch_id, $period, $date)?->load(['requirements', 'items.submitter', 'reviewer']);
            } catch (BriefingBundleConflictException) {
                $conflict = true;
            }
        }

        $obligations = $bundle !== null
            ? $bundle->requirements->sortBy('sort_order')->map(fn (BriefingRecordRequirement $requirement): array => $this->obligationFromSnapshot($requirement, $liveTasks->get($requirement->task_key)))
            : $assigned->map(fn (BriefingTask $task): array => $this->obligationFromTask($task));

        $tasks = $obligations
            ->map(fn (array $obligation): array => $this->taskRow($obligation, $bundle, $conflict))
            ->values()
            ->all();

        return [
            'period' => $period,
            'label' => $period->getLabel(),
            'periodLabel' => $period->periodLabel(),
            'rangeLabel' => $this->rangeLabel($period, $date),
            'total' => count($tasks),
            'completed' => $bundle !== null ? $this->completeness->evaluate($bundle)->completed : 0,
            'bundle' => $this->bundleSummary($bundle, $conflict),
            'tasks' => $tasks,
        ];
    }

    /**
     * @return array{key: string, label: string, type: BriefingSubmissionType, task: ?BriefingTask}
     */
    private function obligationFromSnapshot(BriefingRecordRequirement $requirement, ?BriefingTask $task): array
    {
        return ['key' => $requirement->task_key, 'label' => $requirement->label, 'type' => $requirement->submission_type, 'task' => $task];
    }

    /**
     * @return array{key: string, label: string, type: BriefingSubmissionType, task: ?BriefingTask}
     */
    private function obligationFromTask(BriefingTask $task): array
    {
        return ['key' => $task->key, 'label' => $task->label, 'type' => $task->submission_type, 'task' => $task];
    }

    /**
     * @param  array{key: string, label: string, type: BriefingSubmissionType, task: ?BriefingTask}  $obligation
     * @return array<string, mixed>
     */
    private function taskRow(array $obligation, ?BriefingRecord $bundle, bool $conflict): array
    {
        $task = $obligation['task'];
        $item = $bundle?->items->firstWhere('task_key', $obligation['key']);
        $status = $bundle?->status;
        $isPastDeadline = (bool) $task?->isPastDeadline();
        $isSatisfied = $this->completeness->itemSatisfies($obligation['type'], $item);
        $state = $this->itemState($status, $item, $isSatisfied, $isPastDeadline);

        return [
            'key' => $obligation['key'],
            'label' => $obligation['label'],
            'noteType' => $task?->note_type,
            'submissionType' => $obligation['type']->value,
            'requiresPhoto' => $obligation['type']->requiresPhoto(),
            'group' => $task?->group,
            'groupLabel' => $task?->group_label,
            'itemState' => $state,
            'stateLabel' => $this->stateLabel($state, $status),
            'isEditable' => ! $conflict && $this->isEditable($state, $status, $bundle, $item, $isPastDeadline),
            'isCompleted' => $isSatisfied,
            'completedAt' => $item?->completed_at,
            'submittedByName' => $item?->submitter?->name,
            'photoPaths' => $item?->photo_paths ?? [],
            'notes' => $item?->notes,
            'reviewStatus' => $item?->review_status,
            'rejectionReason' => $state === 'correction' ? ($item?->correction_note ?? $item?->rejection_reason) : null,
            'isPastDeadline' => $state === 'missed',
            'deadlineLabel' => $task?->deadlineLabel(),
        ];
    }

    private function itemState(?BriefingBundleStatus $status, ?BriefingItem $item, bool $isSatisfied, bool $isPastDeadline): string
    {
        return match (true) {
            $status === BriefingBundleStatus::Approved, $item?->review_status === BriefingReviewStatus::Approved => 'approved',
            (bool) $item?->requires_correction, $item?->review_status === BriefingReviewStatus::Rejected => 'correction',
            $isSatisfied => 'submitted',
            $isPastDeadline && $status !== BriefingBundleStatus::Reopened => 'missed',
            default => 'todo',
        };
    }

    private function isEditable(string $state, ?BriefingBundleStatus $status, ?BriefingRecord $bundle, ?BriefingItem $item, bool $isPastDeadline): bool
    {
        if ($status !== null && ! $status->acceptsSubmissions()) {
            return false;
        }

        $isReopened = $status === BriefingBundleStatus::Reopened;
        $correctionsRequested = $isReopened && (bool) $bundle?->items->contains('requires_correction', true);

        if ($correctionsRequested && ! $item?->requires_correction) {
            return false;
        }

        if ($isPastDeadline && ! $isReopened && ! $item?->hasOpenReopenGrant()) {
            return false;
        }

        return match ($state) {
            'todo', 'correction' => true,
            'submitted' => $isReopened && ! $correctionsRequested,
            default => false,
        };
    }

    private function stateLabel(string $state, ?BriefingBundleStatus $status): ?string
    {
        return match ($state) {
            'approved' => BriefingBundleStatus::Approved->getLabel(),
            'correction' => 'Perlu Diperbaiki',
            'submitted' => $status === BriefingBundleStatus::SupervisorReview ? BriefingBundleStatus::SupervisorReview->getLabel() : 'Terkirim',
            'missed' => 'Deadline Terlewat',
            default => null,
        };
    }

    /**
     * @return array{status: ?BriefingBundleStatus, label: string, message: string, tone: string}|null
     */
    private function bundleSummary(?BriefingRecord $bundle, bool $conflict): ?array
    {
        if ($conflict) {
            return ['status' => null, 'label' => 'Perlu Rekonsiliasi', 'message' => 'Terdapat data ganda untuk periode ini. Hubungi HR sebelum melanjutkan.', 'tone' => 'danger'];
        }

        if ($bundle === null) {
            return null;
        }

        return [
            'status' => $bundle->status,
            'label' => $bundle->status->getLabel(),
            'message' => match ($bundle->status) {
                BriefingBundleStatus::Draft => 'Lengkapi semua poin agar briefing dikirim ke supervisor.',
                BriefingBundleStatus::SupervisorReview => 'Semua poin lengkap. Menunggu persetujuan supervisor.',
                BriefingBundleStatus::Approved => 'Disetujui'.($bundle->reviewer ? ' oleh '.$bundle->reviewer->name : '').'. Data tidak dapat diubah.',
                BriefingBundleStatus::Rejected => 'Ditolak'.($bundle->review_notes ? ': '.$bundle->review_notes : '.').' Minta supervisor membuka kembali untuk perbaikan.',
                BriefingBundleStatus::Reopened => 'Dibuka kembali. Perbaiki poin yang ditandai, lalu kirim ulang.',
            },
            'tone' => match ($bundle->status) {
                BriefingBundleStatus::Approved => 'success',
                BriefingBundleStatus::Rejected => 'danger',
                BriefingBundleStatus::SupervisorReview => 'warning',
                default => 'info',
            },
        ];
    }

    private function rangeLabel(BriefingPeriod $period, CarbonInterface $date): string
    {
        $start = $this->periods->start($period, $date);
        $end = $this->periods->end($period, $date);

        return match ($period) {
            BriefingPeriod::Daily => self::ID_DAYS[$start->dayOfWeek].', '.$start->day.' '.self::ID_MONTHS[$start->month].' '.$start->year,
            BriefingPeriod::Weekly => 'Periode '.($start->month === $end->month
                ? $start->day.'–'.$end->day.' '.self::ID_MONTHS[$end->month].' '.$end->year
                : $start->day.' '.self::ID_MONTHS[$start->month].'–'.$end->day.' '.self::ID_MONTHS[$end->month].' '.$end->year),
            BriefingPeriod::Monthly => self::ID_MONTHS[$start->month].' '.$start->year,
        };
    }
}
