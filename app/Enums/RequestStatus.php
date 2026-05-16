<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum RequestStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case InReview = 'in_review';
    case Approved = 'approved';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Diajukan',
            self::InReview => 'Sedang Ditinjau',
            self::Approved => 'Disetujui',
            self::InProgress => 'Sedang Dikerjakan',
            self::Completed => 'Selesai',
            self::Rejected => 'Ditolak',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted => 'info',
            self::InReview => 'warning',
            self::Approved => 'success',
            self::InProgress => 'primary',
            self::Completed => 'success',
            self::Rejected => 'danger',
        };
    }
}
