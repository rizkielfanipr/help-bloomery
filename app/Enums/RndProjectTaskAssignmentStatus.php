<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Per-PIC status of one `rnd_project_task_assignments` record
 * (docs/rnd-project-task-calendar-prd.md §9-10). A Task's own status is aggregated from its
 * active (non-Cancelled) assignments by `ProjectTaskStatusService`.
 */
enum RndProjectTaskAssignmentStatus: string implements HasColor, HasLabel
{
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case RevisionRequired = 'revision_required';
    case Approved = 'approved';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Assigned => 'Assigned',
            self::InProgress => 'In Progress',
            self::Submitted => 'Submitted',
            self::RevisionRequired => 'Revision Required',
            self::Approved => 'Approved',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Status palette of docs/ui-consistency-prd.md §7 — amber waits for review, red was returned
     * for revision; never purple.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::Assigned => 'info',
            self::InProgress => 'info',
            self::Submitted => 'warning',
            self::RevisionRequired => 'danger',
            self::Approved => 'success',
            self::Cancelled => 'gray',
        };
    }

    /**
     * Reminders stop once an assignment reaches one of these statuses
     * (docs/rnd-project-task-calendar-prd.md §16, business rule #16).
     */
    public function stopsReminders(): bool
    {
        return in_array($this, [self::Submitted, self::Approved, self::Cancelled], true);
    }

    /**
     * Statuses that still count as an active obligation for the assignee
     * (docs/rnd-project-task-calendar-prd.md §10, business rule #14).
     */
    public function isActive(): bool
    {
        return $this !== self::Cancelled;
    }
}
