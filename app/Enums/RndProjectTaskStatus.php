<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Workflow status of one `rnd_project_tasks` record (docs/rnd-project-task-calendar-prd.md §9-10).
 * `Overdue` is a derived condition based on due_date, not a case here.
 */
enum RndProjectTaskStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case RevisionRequired = 'revision_required';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Assigned => 'Assigned',
            self::InProgress => 'In Progress',
            self::Submitted => 'Submitted',
            self::RevisionRequired => 'Revision Required',
            self::Completed => 'Completed',
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
            self::Draft => 'gray',
            self::Assigned => 'info',
            self::InProgress => 'info',
            self::Submitted => 'warning',
            self::RevisionRequired => 'danger',
            self::Completed => 'success',
            self::Cancelled => 'gray',
        };
    }

    /**
     * Terminal statuses never transition further (docs/rnd-project-task-calendar-prd.md §10).
     */
    public function isTerminal(): bool
    {
        return $this === self::Completed || $this === self::Cancelled;
    }
}
