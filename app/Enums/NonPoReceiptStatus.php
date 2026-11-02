<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum NonPoReceiptStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case AwaitingPurchasing = 'awaiting_purchasing';
    case PoInProgress = 'po_in_progress';
    case RevisionRequested = 'revision_requested';
    case PoLinked = 'po_linked';
    case GrProcessing = 'gr_processing';
    case GrFailed = 'gr_failed';
    case GrUnknown = 'gr_unknown';
    case QcHold = 'qc_hold';
    case QcRejected = 'qc_rejected';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::AwaitingPurchasing => 'Menunggu Purchasing',
            self::PoInProgress => 'PO Diproses',
            self::RevisionRequested => 'Perlu Perbaikan',
            self::PoLinked => 'PO Terverifikasi',
            self::GrProcessing => 'GR Diproses',
            self::GrFailed => 'GR Gagal',
            self::GrUnknown => 'Perlu Rekonsiliasi',
            self::QcHold => 'QC Hold',
            self::QcRejected => 'QC Rejected',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft, self::Cancelled => 'gray',
            self::AwaitingPurchasing, self::RevisionRequested, self::QcHold => 'warning',
            self::PoInProgress, self::PoLinked, self::GrProcessing => 'info',
            self::GrFailed, self::GrUnknown, self::QcRejected => 'danger',
            self::Completed => 'success',
        };
    }

    /** docs/products/inventory/non-po-receiving/prd.md §13. */
    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Draft => [self::AwaitingPurchasing, self::QcHold, self::QcRejected, self::Cancelled],
            self::AwaitingPurchasing => [self::PoInProgress, self::RevisionRequested, self::Cancelled],
            self::PoInProgress => [self::PoLinked, self::RevisionRequested, self::Cancelled],
            self::RevisionRequested => [self::AwaitingPurchasing, self::QcHold, self::QcRejected, self::Cancelled],
            self::PoLinked => [self::GrProcessing, self::PoInProgress],
            self::GrProcessing => [self::Completed, self::GrFailed, self::GrUnknown],
            self::GrFailed, self::GrUnknown => [self::GrProcessing, self::Completed, self::Cancelled],
            self::QcHold => [self::QcHold, self::AwaitingPurchasing, self::QcRejected, self::Cancelled],
            self::QcRejected, self::Completed, self::Cancelled => [],
        }, true);
    }

    /** Only these statuses let Inventory change the record; anything else goes through a revision. */
    public function canBeEditedByInventory(): bool
    {
        return in_array($this, [self::Draft, self::RevisionRequested, self::QcHold], true);
    }

    public function needsGoodsReceiptResolution(): bool
    {
        return in_array($this, [self::GrFailed, self::GrUnknown], true);
    }

    /** @return list<self> Statuses counted by the Purchasing navigation badge. */
    public static function pendingForPurchasing(): array
    {
        return [self::AwaitingPurchasing, self::PoInProgress, self::RevisionRequested, self::GrFailed, self::GrUnknown];
    }
}
