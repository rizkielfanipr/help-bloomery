<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * docs/rnd-internal-memo-prd.md §6 — Status Memo.
 */
enum RndInternalMemoStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Syncing = 'syncing';
    case NeedsAttention = 'needs_attention';
    case Ready = 'ready';
    case Finalized = 'finalized';
    case Archived = 'archived';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Syncing => 'Syncing',
            self::NeedsAttention => 'Needs Attention',
            self::Ready => 'Ready',
            self::Finalized => 'Finalized',
            self::Archived => 'Archived',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Syncing => 'info',
            self::NeedsAttention => 'danger',
            self::Ready => 'warning',
            self::Finalized => 'success',
            self::Archived => 'gray',
        };
    }

    /**
     * Metadata and Menu selection stay editable only while the memo is a Draft
     * (docs/rnd-internal-memo-prd.md §6 table).
     */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }
}
