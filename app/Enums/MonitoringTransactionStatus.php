<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Lifecycle of one ESB mutation transaction or attempt (docs/operations/monitoring/prd.md §11.2).
 */
enum MonitoringTransactionStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Unknown = 'unknown';
    case Partial = 'partial';
    case Reconciled = 'reconciled';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Succeeded => 'Succeeded',
            self::Failed => 'Failed',
            self::Unknown => 'Unknown',
            self::Partial => 'Partial',
            self::Reconciled => 'Reconciled',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Succeeded, self::Reconciled => 'success',
            self::Failed => 'danger',
            self::Unknown, self::Partial => 'warning',
            self::Pending, self::Processing, self::Cancelled => 'gray',
        };
    }

    public function isFinal(): bool
    {
        return ! in_array($this, [self::Pending, self::Processing], true);
    }

    /**
     * `unknown` must always go through reconciliation, never an automatic retry (§11.6).
     */
    public function needsReconciliation(): bool
    {
        return $this === self::Unknown;
    }

    public function canTransitionTo(self $next): bool
    {
        if ($next === $this) {
            return true;
        }

        return match ($this) {
            self::Pending => in_array($next, [self::Processing, self::Cancelled, self::Failed], true),
            self::Processing => $next->isFinal() && $next !== self::Reconciled,
            self::Unknown, self::Failed, self::Partial => in_array($next, [self::Reconciled, self::Processing], true),
            self::Succeeded, self::Reconciled => $next === self::Processing,
            self::Cancelled => false,
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status): array => [$status->value => $status->getLabel()])->all();
    }
}
