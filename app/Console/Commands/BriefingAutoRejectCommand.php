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
#[Description('Auto-reject briefing items for tasks whose deadline has passed without submission')]
class BriefingAutoRejectCommand extends Command
{
    public function handle(): int
    {
        $tasks = BriefingTask::where('deadline_enabled', true)
            ->where('is_active', true)
            ->get();

        $rejectedCount = 0;

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

                if ($item && ($item->is_completed || in_array($item->review_status, [BriefingReviewStatus::Approved, BriefingReviewStatus::Pending]))) {
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
}
