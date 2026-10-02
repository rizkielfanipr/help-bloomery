<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** docs/store-sales-order-prd.md §11. Operational status only — never sent to or derived from ESB. */
enum StoreSalesOrderStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case InPreparation = 'in_preparation';
    case Ready = 'ready';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::InPreparation => 'In Preparation',
            self::Ready => 'Ready',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted => 'info',
            self::InPreparation => 'warning',
            self::Ready => 'warning',
            self::Delivered => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Delivered, self::Cancelled], true);
    }

    public function requiresCancellationReason(): bool
    {
        return $this === self::Cancelled;
    }

    /**
     * Normal path: Draft -> Submitted -> In Preparation -> Ready -> Delivered. Any non-terminal
     * status may additionally move to Cancelled. Delivered and Cancelled are terminal (§11).
     */
    public function canTransitionTo(self $next): bool
    {
        if ($next === $this) {
            return true;
        }

        if ($this->isTerminal()) {
            return false;
        }

        if ($next === self::Cancelled) {
            return true;
        }

        return match ($this) {
            self::Draft => $next === self::Submitted,
            self::Submitted => $next === self::InPreparation,
            self::InPreparation => $next === self::Ready,
            self::Ready => $next === self::Delivered,
            default => false,
        };
    }
}
