<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * `rnd_project_task_follow_ups.follow_up_type` (docs/rnd-project-task-calendar-prd.md §12).
 * Progress entries never change assignment status; only a Submission does
 * (business rule #12).
 */
enum RndProjectTaskFollowUpType: string implements HasLabel
{
    case Progress = 'progress';
    case Submission = 'submission';

    public function getLabel(): string
    {
        return match ($this) {
            self::Progress => 'Progress',
            self::Submission => 'Submission',
        };
    }
}
