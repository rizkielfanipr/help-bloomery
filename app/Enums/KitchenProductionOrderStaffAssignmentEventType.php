<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Audit event of a Staff Produksi assignment (docs/products/kitchen/production-staff-assignment/prd.md §14.2).
 */
enum KitchenProductionOrderStaffAssignmentEventType: string implements HasLabel
{
    case Assigned = 'assigned';
    case Unassigned = 'unassigned';

    public function getLabel(): string
    {
        return match ($this) {
            self::Assigned => 'Ditugaskan',
            self::Unassigned => 'Dilepas',
        };
    }
}
