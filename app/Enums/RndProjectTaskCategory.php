<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * `rnd_project_tasks.task_type` — fixed category list (docs/rnd-project-task-calendar-prd.md §9).
 * Dynamic/custom categories are not part of this version.
 */
enum RndProjectTaskCategory: string implements HasLabel
{
    case Trial = 'trial';
    case Tasting = 'tasting';
    case QualityControl = 'quality_control';
    case Approval = 'approval';
    case Production = 'production';
    case ProductPhotography = 'product_photography';
    case Launching = 'launching';
    case General = 'general';

    public function getLabel(): string
    {
        return match ($this) {
            self::Trial => 'Trial',
            self::Tasting => 'Tasting',
            self::QualityControl => 'Quality Control',
            self::Approval => 'Approval',
            self::Production => 'Production',
            self::ProductPhotography => 'Product Photography',
            self::Launching => 'Launching',
            self::General => 'General',
        };
    }
}
