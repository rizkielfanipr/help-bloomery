<?php

namespace App\Models;

use App\Enums\RndProjectTaskPriority;
use App\Enums\RndProjectTaskStatus;
use Database\Factories\RndProjectTaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One R&D operational task attached to a Project, shared to one or more Branches, each with its
 * own PIC assignment (docs/rnd-project-task-calendar-prd.md §8.1). Task rows are never soft-deleted
 * — child tables of `RndProject` (Product, Bom) hard-delete in this codebase, and the `Cancelled`
 * status already preserves history without a `deleted_at` column.
 */
class RndProjectTask extends Model
{
    /** @use HasFactory<RndProjectTaskFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'rnd_project_id',
        'title',
        'task_type',
        'description',
        'assigned_date',
        'due_date',
        'priority',
        'status',
        'instruction_attachments',
        'created_by',
        'completed_by',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_date' => 'date',
            'due_date' => 'date',
            'priority' => RndProjectTaskPriority::class,
            'status' => RndProjectTaskStatus::class,
            'instruction_attachments' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(RndProject::class, 'rnd_project_id');
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'rnd_project_task_branches');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(RndProjectTaskAssignment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Assignments that still count toward status aggregation and reminders
     * (business rule #14 — Cancelled assignments are excluded).
     */
    public function activeAssignments(): HasMany
    {
        return $this->assignments()->whereNot('status', 'cancelled');
    }

    public function isOverdue(): bool
    {
        return ! $this->status->isTerminal() && $this->due_date->lt(today());
    }

    /**
     * Business rule #11 — the deadline must never be earlier than the assign date.
     */
    public function hasValidDeadline(): bool
    {
        return $this->due_date->gte($this->assigned_date);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
