<?php

namespace App\Models;

use App\Enums\RndProjectTaskAssignmentStatus;
use Database\Factories\RndProjectTaskAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One PIC's obligation for a Task on one Branch (docs/rnd-project-task-calendar-prd.md §8.3).
 * `user_id` is a snapshot: it is never revoked just because the user later loses access to
 * `branch_id` (business rule #6).
 */
class RndProjectTaskAssignment extends Model
{
    /** @use HasFactory<RndProjectTaskAssignmentFactory> */
    use HasFactory;

    protected $fillable = [
        'rnd_project_task_id',
        'branch_id',
        'user_id',
        'status',
        'assigned_at',
        'started_at',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'status' => RndProjectTaskAssignmentStatus::class,
            'assigned_at' => 'datetime',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(RndProjectTask::class, 'rnd_project_task_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(RndProjectTaskFollowUp::class)->latest();
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(RndProjectTaskReminder::class);
    }
}
