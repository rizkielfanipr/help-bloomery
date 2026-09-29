<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Mutation attempt status of one `rnd_bom_change_logs` record (docs/rnd-bom-adjustment-prd.md §14).
 */
enum RndBomChangeLogStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
    case NeedsReconciliation = 'needs_reconciliation';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Success => 'Success',
            self::Failed => 'Failed',
            self::NeedsReconciliation => 'Needs Reconciliation',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Success => 'success',
            self::Failed => 'danger',
            self::NeedsReconciliation => 'warning',
        };
    }

    /**
     * A result is uncertain (timeout/connection failure) and must not be retried automatically
     * until reconciliation confirms the true outcome (docs/rnd-bom-adjustment-prd.md §14).
     */
    public function isUncertain(): bool
    {
        return $this === self::NeedsReconciliation;
    }
}
