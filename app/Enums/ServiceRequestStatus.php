<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ServiceRequestStatus: string implements HasColor, HasIcon, HasLabel
{
    case Submitted = 'submitted';
    case Scheduled = 'scheduled';
    case AwaitingParts = 'awaiting_parts';
    case Outsource = 'outsource';
    case AwaitingVerification = 'awaiting_verification';
    case InProgress = 'in_progress';
    case Warranty = 'warranty';
    case ReSubmitted = 're_submitted';
    case Completed = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::Scheduled => 'Scheduled',
            self::AwaitingParts => 'Awaiting Parts',
            self::Outsource => 'Outsource',
            self::AwaitingVerification => 'Awaiting Verification',
            self::InProgress => 'In Progress',
            self::Warranty => 'Warranty',
            self::ReSubmitted => 'Re-submitted',
            self::Completed => 'Completed',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Submitted => 'info',
            self::Scheduled => 'info',
            self::AwaitingParts => 'warning',
            self::Outsource => 'warning',
            self::AwaitingVerification => 'info',
            self::InProgress => 'warning',
            self::Warranty => 'purple',
            self::ReSubmitted => 'danger',
            self::Completed => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Submitted => 'heroicon-o-paper-airplane',
            self::Scheduled => 'heroicon-o-calendar-days',
            self::AwaitingParts => 'heroicon-o-clock',
            self::Outsource => 'heroicon-o-building-office',
            self::AwaitingVerification => 'heroicon-o-clipboard-document-check',
            self::InProgress => 'heroicon-o-wrench-screwdriver',
            self::Warranty => 'heroicon-o-shield-check',
            self::ReSubmitted => 'heroicon-o-arrow-path',
            self::Completed => 'heroicon-o-check-badge',
        };
    }
}
