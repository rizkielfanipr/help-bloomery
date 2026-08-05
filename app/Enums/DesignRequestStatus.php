<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DesignRequestStatus: string implements HasColor, HasLabel
{
    case DesignRequest = 'design_request';
    case InProgress = 'in_progress';
    case Approval = 'approval';
    case PrintingProcess = 'printing_process';
    case Completed = 'completed';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::DesignRequest => 'Design Request',
            self::InProgress => 'In Progress',
            self::Approval => 'Approval',
            self::PrintingProcess => 'Printing Process',
            self::Completed => 'Completed',
            self::Rejected => 'Rejected',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DesignRequest => 'gray',
            self::InProgress => 'info',
            self::Approval => 'warning',
            self::PrintingProcess => 'primary',
            self::Completed => 'success',
            self::Rejected => 'danger',
        };
    }

    /** @return array<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DesignRequest => [self::InProgress],
            self::InProgress => [self::Approval],
            self::Approval => [self::PrintingProcess, self::Rejected],
            self::PrintingProcess => [self::Completed],
            self::Completed, self::Rejected => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }
}
