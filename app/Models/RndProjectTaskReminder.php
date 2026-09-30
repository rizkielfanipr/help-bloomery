<?php

namespace App\Models;

use App\Enums\RndProjectTaskReminderType;
use Database\Factories\RndProjectTaskReminderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dedup log for one deadline-proximity reminder send (docs/rnd-project-task-calendar-prd.md §8.6,
 * §16). The `rnd_task_reminder_unique` constraint on assignment_id+reminder_type+reminder_date is
 * the source of truth for idempotency when the scheduler/Job reruns.
 */
class RndProjectTaskReminder extends Model
{
    /** @use HasFactory<RndProjectTaskReminderFactory> */
    use HasFactory;

    protected $fillable = [
        'rnd_project_task_assignment_id',
        'reminder_type',
        'reminder_date',
        'sent_at',
        'notification_id',
    ];

    protected function casts(): array
    {
        return [
            'reminder_type' => RndProjectTaskReminderType::class,
            'reminder_date' => 'date',
            'sent_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(RndProjectTaskAssignment::class, 'rnd_project_task_assignment_id');
    }
}
