<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Per-Menu BOM sync status stored on `rnd_internal_memo_menus.sync_status`
 * (docs/rnd-internal-memo-prd.md §12.2, §7.4).
 */
enum RndInternalMemoMenuSyncStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Syncing = 'syncing';
    case Synced = 'synced';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Belum Disinkron',
            self::Syncing => 'Sinkronisasi...',
            self::Synced => 'Tersinkron',
            self::Failed => 'Gagal',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Syncing => 'info',
            self::Synced => 'success',
            self::Failed => 'danger',
        };
    }
}
