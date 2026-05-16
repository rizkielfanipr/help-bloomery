<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DeliveryStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Partial = 'partial';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Delivered => 'Terkirim',
            self::Failed => 'Gagal',
            self::Partial => 'Sebagian',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Delivered => 'success',
            self::Failed => 'danger',
            self::Partial => 'info',
        };
    }
}
