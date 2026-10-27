<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * ESB identity of a Master Bahan (§11.2). `synced` is only written after the product was read
 * back and every active local unit maps to exactly one Product Detail ID.
 */
enum MaterialEsbSyncStatus: string implements HasColor, HasLabel
{
    case NotReady = 'not_ready';
    case Queued = 'queued';
    case Syncing = 'syncing';
    case Synced = 'synced';
    case Failed = 'failed';
    case NeedsReconciliation = 'needs_reconciliation';

    public function getLabel(): string
    {
        return match ($this) {
            self::NotReady => 'Not Ready',
            self::Queued => 'Queued',
            self::Syncing => 'Syncing',
            self::Synced => 'Synced',
            self::Failed => 'Failed',
            self::NeedsReconciliation => 'Needs Reconciliation',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NotReady => 'gray',
            self::Queued, self::Syncing => 'info',
            self::Synced => 'success',
            self::Failed => 'danger',
            self::NeedsReconciliation => 'warning',
        };
    }

    /**
     * Value written to the legacy `status` column so existing readers keep working during the
     * compatibility window.
     */
    public function legacyStatus(): string
    {
        return match ($this) {
            self::NotReady => 'draft',
            self::Queued, self::Syncing => 'syncing',
            self::Synced => 'synced',
            self::Failed => 'failed',
            self::NeedsReconciliation => 'unknown',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status): array => [$status->value => $status->getLabel()])->all();
    }
}
