<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class ItBusinessHoursService
{
    public const TIMEZONE = 'Asia/Jakarta';

    public function secondsBetween(?CarbonInterface $start, ?CarbonInterface $end): ?int
    {
        if (! $start || ! $end || $end->lessThan($start)) {
            return null;
        }

        return (int) array_sum(array_column($this->breakdown($start, $end), 'seconds'));
    }

    /** @return list<array{date: string, start: string, end: string, seconds: int}> */
    public function breakdown(CarbonInterface $start, CarbonInterface $end): array
    {
        $start = CarbonImmutable::instance($start)->setTimezone(self::TIMEZONE);
        $end = CarbonImmutable::instance($end)->setTimezone(self::TIMEZONE);
        $days = [];

        for ($day = $start->startOfDay(); $day->lessThanOrEqualTo($end); $day = $day->addDay()) {
            if ($day->isWeekend()) {
                continue;
            }

            $overlapStart = $day->setTime(8, 0)->max($start);
            $overlapEnd = $day->setTime(17, 0)->min($end);
            if ($overlapEnd->lessThanOrEqualTo($overlapStart)) {
                continue;
            }

            $days[] = [
                'date' => $day->format('d/m/Y'),
                'start' => $overlapStart->format('H:i:s'),
                'end' => $overlapEnd->format('H:i:s'),
                'seconds' => (int) $overlapStart->diffInSeconds($overlapEnd),
            ];
        }

        return $days;
    }

    public function formatDuration(int|float|null $seconds): string
    {
        if ($seconds === null) {
            return 'Tidak tersedia';
        }

        $seconds = (int) round($seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        return $hours.' jam '.$minutes.' menit'.($remainingSeconds > 0 ? ' '.$remainingSeconds.' detik' : '');
    }
}
