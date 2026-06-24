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

    public function getLabel(): string
    {
        return match ($this) {
            self::DailySelfiePagi => 'Absen Briefing Pagi (09.00)',
            self::DailySelfieSore => 'Absen Briefing Sore (15.30)',
            self::DailyDetailBriefing => 'Detail Briefing',
            self::WeeklyCleaning => 'Weekly Cleaning',
            self::WeeklyWmPic => 'Weekly Meeting PIC (Evaluasi Staff, Administrasi dan Sales)',
            self::WeeklySchedule => 'Weekly Schedule',
            self::MonthlyGmKpi => 'General Meeting: Key Performance Indicator (KPI)',
            self::MonthlyGeneralCleaning => 'General Cleaning',
        };
    }

    public function noteType(): string
    {
        return match ($this) {
            self::DailySelfiePagi, self::DailySelfieSore => 'Foto Selfie Briefing',
            self::DailyDetailBriefing => 'Foto Screenshot Detail Briefing',
            self::WeeklyCleaning, self::MonthlyGeneralCleaning => 'Foto Area yang Dibersihkan',
            self::WeeklyWmPic, self::MonthlyGmKpi => 'Foto Weekly Meeting PIC / General Meeting',
            self::WeeklySchedule => 'Foto Jadwal Mingguan',
        };
    }

    public function requiresPhoto(): bool
    {
        return true;
    }

    public function isHrChecked(): bool
    {
        return false;
    }

    public function period(): BriefingPeriod
    {
        return match ($this) {
            self::DailySelfiePagi, self::DailySelfieSore, self::DailyDetailBriefing => BriefingPeriod::Daily,
            self::WeeklyCleaning, self::WeeklyWmPic, self::WeeklySchedule => BriefingPeriod::Weekly,
            self::MonthlyGmKpi, self::MonthlyGeneralCleaning => BriefingPeriod::Monthly,
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
