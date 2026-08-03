<?php

namespace App\Console\Commands;

use App\Enums\BriefingReviewStatus;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use App\Models\BriefingTask;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('briefing:auto-reject')]
#[Description('Auto-reject overdue briefing submissions and items pending review for seven days')]
class BriefingAutoRejectCommand extends Command
{
    public function handle(): int
    {
        $rejectedCount = $this->rejectExpiredPendingReviews();

        $tasks = BriefingTask::where('deadline_enabled', true)
            ->where('is_active', true)
            ->get();

        foreach ($tasks as $task) {
            if (! $task->isPastDeadline()) {
                continue;
            }

            $period = $task->period;
            $recordDate = $period->recordDate();

            $users = User::where('is_active', true)
                ->when(
                    $task->branch_id !== null,
                    fn ($q) => $q->where('branch_id', $task->branch_id)
                )
                ->get();

            foreach ($users as $user) {
                $record = BriefingRecord::where('user_id', $user->id)
                    ->where('period', $period->value)
                    ->whereDate('record_date', $recordDate)
                    ->first()
                    ?? BriefingRecord::create([
                        'user_id' => $user->id,
                        'period' => $period->value,
                        'record_date' => $recordDate,
                    ]);

                $item = BriefingItem::where('briefing_record_id', $record->id)
                    ->where('task_key', $task->key)
                    ->first();

                if ($item && ($item->is_completed || $item->review_status === BriefingReviewStatus::Approved || ($item->review_status?->isAwaitingSupervisorReview() ?? false))) {
                    continue;
                }

                if (! $item) {
                    $item = new BriefingItem([
                        'briefing_record_id' => $record->id,
                        'task_key' => $task->key,
                    ]);
                }

                $item->fill([
                    'is_completed' => false,
                    'completed_at' => null,
                    'review_status' => BriefingReviewStatus::Rejected->value,
                    'rejection_reason' => 'Tidak ada input sebelum deadline.',
                    'reviewed_by' => null,
                    'reviewed_at' => now(),
                ])->save();

                $rejectedCount++;
            }
        }

        $this->info("Auto-rejected {$rejectedCount} briefing item(s).");

        return Command::SUCCESS;
    }

    private function rejectExpiredPendingReviews(): int
    {
        $rejectedCount = 0;

        BriefingItem::query()
            ->whereIn('review_status', [
                BriefingReviewStatus::LegacyPending->value,
                BriefingReviewStatus::SupervisorReview->value,
            ])
            ->where('is_completed', true)
            ->whereNotNull('completed_at')
            ->where('completed_at', '<=', now()->subWeek())
            ->chunkById(100, function ($items) use (&$rejectedCount): void {
                foreach ($items as $item) {
                    $item->update([
                        'review_status' => BriefingReviewStatus::Rejected->value,
                        'rejection_reason' => 'Tidak ada approval dalam 7 hari setelah poin diselesaikan.',
                        'reviewed_by' => null,
                        'reviewed_at' => now(),
                    ]);

                    $rejectedCount++;
                }
            });

        return $rejectedCount;
    }
}
