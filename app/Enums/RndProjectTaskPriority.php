<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * `rnd_project_tasks.priority` (docs/rnd-project-task-calendar-prd.md §9). Default is Medium.
 */
enum RndProjectTaskPriority: string implements HasColor, HasLabel
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Urgent = 'urgent';

    public function getLabel(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
            self::Urgent => 'Urgent',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Low => 'gray',
            self::Medium => 'info',
            self::High => 'warning',
            self::Urgent => 'danger',
        };
    }

    /**
     * Priorities that qualify a task for the dashboard's "needs attention" ordering
     * (docs/rnd-project-task-calendar-prd.md §15).
     */
    public function isUrgentOrHigh(): bool
    {
        return $this === self::Urgent || $this === self::High;
    }
}
