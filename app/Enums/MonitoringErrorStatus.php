<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Triage state of one error fingerprint (docs/operations/monitoring/prd.md §13.3).
 */
enum MonitoringErrorStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case Acknowledged = 'acknowledged';
    case Investigating = 'investigating';
    case Resolved = 'resolved';
    case Ignored = 'ignored';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Acknowledged => 'Acknowledged',
            self::Investigating => 'Investigating',
            self::Resolved => 'Resolved',
            self::Ignored => 'Ignored',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'danger',
            self::Acknowledged, self::Investigating => 'warning',
            self::Resolved => 'success',
            self::Ignored => 'gray',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status): array => [$status->value => $status->getLabel()])->all();
    }
}
