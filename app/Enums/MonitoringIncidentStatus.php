<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Incident workflow Open → Acknowledged → Investigating → Monitoring → Resolved, with reopen
 * (docs/operations/monitoring/prd.md §16).
 */
enum MonitoringIncidentStatus: string implements HasColor, HasLabel
{
    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case Investigating = 'investigating';
    case Monitoring = 'monitoring';
    case Resolved = 'resolved';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Acknowledged => 'Acknowledged',
            self::Investigating => 'Investigating',
            self::Monitoring => 'Monitoring',
            self::Resolved => 'Resolved',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'danger',
            self::Acknowledged, self::Investigating => 'warning',
            self::Monitoring => 'info',
            self::Resolved => 'success',
        };
    }

    public function isActive(): bool
    {
        return $this !== self::Resolved;
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::Acknowledged, self::Investigating, self::Resolved],
            self::Acknowledged => [self::Investigating, self::Monitoring, self::Resolved],
            self::Investigating => [self::Monitoring, self::Resolved],
            self::Monitoring => [self::Investigating, self::Resolved],
            self::Resolved => [self::Open],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status): array => [$status->value => $status->getLabel()])->all();
    }
}
