<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ServiceRequestStatus: string implements HasColor, HasIcon, HasLabel
{
    case Submitted = 'submitted';
    case InProgress = 'in_progress';
    case Warranty = 'warranty';
    case ReSubmitted = 're_submitted';
    case Completed = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Submitted => 'Diajukan',
            self::InProgress => 'Dikerjakan',
            self::Warranty => 'Garansi',
            self::ReSubmitted => 'Pengaduan Ulang',
            self::Completed => 'Selesai',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Submitted => 'info',
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
            self::InProgress => 'heroicon-o-wrench-screwdriver',
            self::Warranty => 'heroicon-o-shield-check',
            self::ReSubmitted => 'heroicon-o-arrow-path',
            self::Completed => 'heroicon-o-check-badge',
        };
    }
}
