<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Sync status of one `rnd_bom_catalogs` snapshot row (docs/rnd-bom-adjustment-prd.md §8).
 */
enum RndBomCatalogSyncStatus: string implements HasColor, HasLabel
{
    case Synced = 'synced';
    case Failed = 'failed';
    case NeedsReconciliation = 'needs_reconciliation';

    public function getLabel(): string
    {
        return match ($this) {
            self::Synced => 'Tersinkron',
            self::Failed => 'Gagal',
            self::NeedsReconciliation => 'Perlu Rekonsiliasi',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Synced => 'success',
            self::Failed => 'danger',
            self::NeedsReconciliation => 'warning',
        };
    }
}
