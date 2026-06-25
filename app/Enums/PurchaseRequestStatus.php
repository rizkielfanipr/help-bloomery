<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PurchaseRequestStatus: string implements HasColor, HasLabel
{
    case Submitted = 'submitted';
    case InProcess = 'in_process';
    case Purchased = 'purchased';
    case Delivered = 'delivered';
    case Completed = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Submitted => 'Diajukan',
            self::InProcess => 'Diproses',
            self::Purchased => 'Sudah Dibeli',
            self::Delivered => 'Dikirim',
            self::Completed => 'Selesai',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Submitted => 'gray',
            self::InProcess => 'warning',
            self::Purchased => 'info',
            self::Delivered => 'primary',
            self::Completed => 'success',
        };
    }

    public function nextStatus(): ?self
    {
        return match ($this) {
            self::Submitted => self::InProcess,
            self::InProcess => self::Purchased,
            self::Purchased => self::Delivered,
            self::Delivered => self::Completed,
            self::Completed => null,
        };
    }
}
