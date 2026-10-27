<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Delivery log of one external notification (e.g. Telegram) (§14.3). `skipped` records that the
 * channel was disabled or not configured, so no HTTP request was made.
 */
enum NotificationDeliveryStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Sent => 'Sent',
            self::Failed => 'Failed',
            self::Skipped => 'Skipped (Not Configured)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Sent => 'success',
            self::Failed => 'danger',
            self::Skipped => 'gray',
        };
    }
}
