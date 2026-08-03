<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PurchaseRequestStatus: string implements HasColor, HasLabel
{
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Purchased = 'purchased';
    case Delivered = 'delivered';
    case Completed = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Purchased => 'Purchased',
            self::Delivered => 'Delivered',
            self::Completed => 'Completed',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Submitted => 'gray',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Purchased => 'info',
            self::Delivered => 'primary',
            self::Completed => 'success',
        };
    }

    /** @return array<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Submitted => [self::Approved, self::Rejected],
            self::Approved => [self::Purchased],
            self::Purchased => [self::Delivered],
            self::Delivered => [self::Completed],
            self::Rejected, self::Completed => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    public function nextStatus(): ?self
    {
        $transitions = $this->allowedTransitions();

        return count($transitions) === 1 ? $transitions[0] : null;
    }
}
