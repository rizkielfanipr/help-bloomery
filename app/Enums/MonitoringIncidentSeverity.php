<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Incident severity SEV-1 to SEV-4 (docs/products/system-monitoring/prd.md §16).
 */
enum MonitoringIncidentSeverity: string implements HasColor, HasLabel
{
    case Sev1 = 'sev1';
    case Sev2 = 'sev2';
    case Sev3 = 'sev3';
    case Sev4 = 'sev4';

    public function getLabel(): string
    {
        return match ($this) {
            self::Sev1 => 'SEV-1',
            self::Sev2 => 'SEV-2',
            self::Sev3 => 'SEV-3',
            self::Sev4 => 'SEV-4',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Sev1 => 'Layanan utama down atau risiko data luas',
            self::Sev2 => 'Fungsi penting terganggu tanpa workaround memadai',
            self::Sev3 => 'Degradasi terbatas atau ada workaround',
            self::Sev4 => 'Informasi/peringatan non-mendesak',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Sev1 => 'danger',
            self::Sev2 => 'warning',
            self::Sev3 => 'info',
            self::Sev4 => 'gray',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $severity): array => [$severity->value => $severity->getLabel().' — '.$severity->description()])->all();
    }
}
