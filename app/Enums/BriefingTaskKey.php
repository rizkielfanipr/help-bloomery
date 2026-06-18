<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum BriefingTaskKey: string implements HasLabel
{
    // Daily
    case DailySelfiePagi = 'daily_selfie_pagi';
    case DailySelfieSore = 'daily_selfie_sore';
    case DailyDetailBriefing = 'daily_detail_briefing';

    // Weekly
    case WeeklyCleaning = 'weekly_cleaning';
    case WeeklyWmPic = 'weekly_wm_pic';
    case WeeklySchedule = 'weekly_schedule';

    // Monthly
    case MonthlyGmKpi = 'monthly_gm_kpi';
    case MonthlyGeneralCleaning = 'monthly_general_cleaning';
    case MonthlyMilestone = 'monthly_milestone';
    case MonthlyKpi = 'monthly_kpi';

    public function getLabel(): string
    {
        return match ($this) {
            self::DailySelfiePagi => 'Absen briefing & kesiapan opening pagi (Maks jam 09.00)',
            self::DailySelfieSore => 'Absen briefing & kesiapan opening sore (Maks jam 15.30)',
            self::DailyDetailBriefing => 'Detail briefing (Maks jam 10.00)',
            self::WeeklyCleaning => 'Weekly Cleaning',
            self::WeeklyWmPic => 'WM PIC — Evaluasi staffing, administrasi, target sales',
            self::WeeklySchedule => 'Schedule',
            self::MonthlyGmKpi => 'GM Store: KPI',
            self::MonthlyGeneralCleaning => 'General Cleaning',
            self::MonthlyMilestone => 'Pengisian Milestone',
            self::MonthlyKpi => 'Pengisian KPI',
        };
    }

    public function noteType(): string
    {
        return match ($this) {
            self::DailySelfiePagi, self::DailySelfieSore => 'Selfie briefing',
            self::DailyDetailBriefing => 'Foto Screenshot',
            self::WeeklyCleaning, self::MonthlyGeneralCleaning => 'Foto area yang dibersihkan',
            self::WeeklyWmPic, self::MonthlyGmKpi => 'Foto',
            self::WeeklySchedule => 'Foto/Dokumen',
            self::MonthlyMilestone, self::MonthlyKpi => 'Cek by HR',
        };
    }

    public function requiresPhoto(): bool
    {
        return ! in_array($this, [self::MonthlyMilestone, self::MonthlyKpi]);
    }

    public function isHrChecked(): bool
    {
        return in_array($this, [self::MonthlyMilestone, self::MonthlyKpi]);
    }

    public function period(): BriefingPeriod
    {
        return match ($this) {
            self::DailySelfiePagi, self::DailySelfieSore, self::DailyDetailBriefing => BriefingPeriod::Daily,
            self::WeeklyCleaning, self::WeeklyWmPic, self::WeeklySchedule => BriefingPeriod::Weekly,
            self::MonthlyGmKpi, self::MonthlyGeneralCleaning, self::MonthlyMilestone, self::MonthlyKpi => BriefingPeriod::Monthly,
        };
    }

    /** @return self[] */
    public static function forPeriod(BriefingPeriod $period): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $task) => $task->period() === $period,
        ));
    }
}
