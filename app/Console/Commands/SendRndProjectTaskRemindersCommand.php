<?php

namespace App\Console\Commands;

use App\Jobs\Rnd\ProjectTask\SendProjectTaskReminderJob;
use App\Services\Rnd\ProjectTask\ProjectTaskReminderService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('rnd:send-project-task-reminders')]
#[Description('Dispatch deadline-proximity reminders (3 days, 1 day, due today, overdue) for active R&D Task assignments')]
class SendRndProjectTaskRemindersCommand extends Command
{
    public function handle(ProjectTaskReminderService $reminderService): int
    {
        $due = $reminderService->dueReminders();

        foreach ($due as $reminder) {
            SendProjectTaskReminderJob::dispatch($reminder['assignment_id'], $reminder['reminder_type']);
        }

        $this->info("Dispatched {$due->count()} Task reminder job(s).");

        return Command::SUCCESS;
    }
}
