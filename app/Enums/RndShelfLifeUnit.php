<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Shelf Life unit of the WIP master (docs/rnd-wip-shelf-life-prd.md §12.4). Legacy values written
 * by the old Menu master (`jam/hari/minggu/bulan`) are only ever read through `fromLegacy()`, never
 * written again.
 */
enum RndShelfLifeUnit: string implements HasLabel
{
    case Hour = 'hour';
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';

    /**
     * Explicit legacy → internal key map. Anything not listed is reported, never guessed.
     */
    private const LEGACY_VALUES = [
        'jam' => 'hour',
        'hari' => 'day',
        'minggu' => 'week',
        'bulan' => 'month',
        'tahun' => 'year',
    ];

    public function getLabel(): string
    {
        return match ($this) {
            self::Hour => 'Jam',
            self::Day => 'Hari',
            self::Week => 'Minggu',
            self::Month => 'Bulan',
            self::Year => 'Tahun',
        };
    }

    /**
     * Maps a stored unit (current key or registered legacy label) to the enum; null for unknown
     * values so callers can report them.
     */
    public static function fromLegacy(?string $value): ?self
    {
        $normalized = mb_strtolower(trim((string) $value));

        return self::tryFrom($normalized) ?? self::tryFrom(self::LEGACY_VALUES[$normalized] ?? '');
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $unit): array => [$unit->value => $unit->getLabel()])->all();
    }
}
