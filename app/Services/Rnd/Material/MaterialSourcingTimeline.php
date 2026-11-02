<?php

namespace App\Services\Rnd\Material;

use App\Enums\MaterialSourcingStatus;
use App\Models\RndProductEsbMaterial;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-side presenter of sourcing dates and calendar duration
 * (docs/products/purchasing/sourcing-duration-tracking/prd.md §11, §17). It only reads attributes of
 * the given record, so index rows never run a query per row. `sourcing_status` decides whether the
 * record is running or final; the duration is never stored.
 */
class MaterialSourcingTimeline
{
    public const NOT_AVAILABLE = 'Tidak Tersedia';

    public const NOT_APPLICABLE = '—';

    public const STATE_RUNNING = 'running';

    public const STATE_COMPLETED = 'completed';

    private const DATE_FORMAT = 'd M Y H:i';

    public static function startedLabel(RndProductEsbMaterial $material): string
    {
        if (! self::tracksDuration($material)) {
            return self::NOT_APPLICABLE;
        }

        return $material->sourcing_requested_at?->format(self::DATE_FORMAT) ?? self::NOT_AVAILABLE;
    }

    public static function completedLabel(RndProductEsbMaterial $material): string
    {
        if (! self::tracksDuration($material) || ! $material->sourcing_status->isSourcingFinal()) {
            return self::NOT_APPLICABLE;
        }

        return $material->sourcing_completed_at?->format(self::DATE_FORMAT) ?? self::NOT_AVAILABLE;
    }

    public static function durationLabel(RndProductEsbMaterial $material, ?CarbonInterface $now = null): string
    {
        if (! self::tracksDuration($material)) {
            return self::NOT_APPLICABLE;
        }

        $startedAt = $material->sourcing_requested_at;
        if ($startedAt === null) {
            return self::NOT_AVAILABLE;
        }

        if ($material->sourcing_status->isSourcingFinal()) {
            return $material->sourcing_completed_at === null
                ? self::NOT_AVAILABLE
                : self::formatSeconds($material->sourcing_completed_at->getTimestamp() - $startedAt->getTimestamp()) ?? self::NOT_AVAILABLE;
        }

        $running = self::formatSeconds(($now ?? now())->getTimestamp() - $startedAt->getTimestamp());

        return $running === null ? self::NOT_AVAILABLE : $running.' berjalan';
    }

    /**
     * Whole minutes under one hour, then the relevant days and hours; never rounded up. Null for a
     * negative span, which only invalid chronology can produce.
     */
    public static function formatSeconds(int $seconds): ?string
    {
        if ($seconds < 0) {
            return null;
        }
        if ($seconds < 60) {
            return 'Kurang dari 1 menit';
        }
        if ($seconds < 3600) {
            return intdiv($seconds, 60).' menit';
        }

        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);

        return trim(($days > 0 ? "{$days} hari " : '').($hours > 0 ? "{$hours} jam" : ''));
    }

    /**
     * Applies the "Kondisi Durasi" filter, by the same status rule the labels use.
     */
    public static function scopeState(Builder $query, ?string $state): Builder
    {
        $values = fn (array $statuses): array => array_map(fn (MaterialSourcingStatus $status): string => $status->value, $statuses);

        return match ($state) {
            self::STATE_RUNNING => $query->whereNotIn('sourcing_status', $values([...MaterialSourcingStatus::finalCases(), ...MaterialSourcingStatus::withoutDurationCases()])),
            self::STATE_COMPLETED => $query->whereIn('sourcing_status', $values(MaterialSourcingStatus::finalCases())),
            default => $query,
        };
    }

    private static function tracksDuration(RndProductEsbMaterial $material): bool
    {
        return $material->sourcing_status !== null
            && ! in_array($material->sourcing_status, MaterialSourcingStatus::withoutDurationCases(), true);
    }
}
