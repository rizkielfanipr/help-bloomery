<?php

namespace App\Services;

use App\Enums\BriefingPeriod;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * docs/daily-briefing-bundle-approval-prd.md §7.1: the single place that turns any date inside an
 * obligation into its bundle's period_start / period_end. The business week is Monday–Sunday,
 * pinned explicitly so it never follows the app locale.
 */
class BriefingPeriodNormalizer
{
    public function start(BriefingPeriod $period, CarbonInterface $date): CarbonImmutable
    {
        $day = CarbonImmutable::instance($date)->startOfDay();

        return match ($period) {
            BriefingPeriod::Daily => $day,
            BriefingPeriod::Weekly => $day->startOfWeek(CarbonInterface::MONDAY),
            BriefingPeriod::Monthly => $day->startOfMonth(),
        };
    }

    public function end(BriefingPeriod $period, CarbonInterface $date): CarbonImmutable
    {
        $start = $this->start($period, $date);

        return match ($period) {
            BriefingPeriod::Daily => $start,
            BriefingPeriod::Weekly => $start->addDays(6),
            BriefingPeriod::Monthly => $start->endOfMonth()->startOfDay(),
        };
    }
}
