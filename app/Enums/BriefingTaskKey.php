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
    case MonthlyGeneralCleaning = 'monthly_general_cleaning'; // deprecated — kept for old records

    // Monthly — General Cleaning (per item)
    case MonthlyCleaningChiller = 'monthly_cleaning_chiller';
    case MonthlyCleaningFloor = 'monthly_cleaning_floor';
    case MonthlyCleaningFreezer = 'monthly_cleaning_freezer';
    case MonthlyCleaningSink = 'monthly_cleaning_sink';
    case MonthlyCleaningEquipment = 'monthly_cleaning_equipment';
    case MonthlyCleaningCoffee = 'monthly_cleaning_coffee';
    case MonthlyCleaningSofa = 'monthly_cleaning_sofa';
    case MonthlyCleaningDiscard = 'monthly_cleaning_discard';
    case MonthlyCleaningDocs = 'monthly_cleaning_docs';
    case MonthlyCleaningSpecific = 'monthly_cleaning_specific';

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
            self::MonthlyCleaningChiller => 'Cuci Komponen Showcase Chiller & Display',
            self::MonthlyCleaningFloor => 'Bersihkan Lantai & Dinding dari Spot Hitam',
            self::MonthlyCleaningFreezer => 'Kuras Bunga Es Freezer',
            self::MonthlyCleaningSink => 'Poles Sink & Wastafel',
            self::MonthlyCleaningEquipment => 'Deep Cleaning Dispenser, Kompor & Blender',
            self::MonthlyCleaningCoffee => 'Deep Cleaning Mesin Kopi',
            self::MonthlyCleaningSofa => 'Vacuum Sofa',
            self::MonthlyCleaningDiscard => 'Sortir & Singkirkan Barang Tidak Terpakai',
            self::MonthlyCleaningDocs => 'Rapikan File Sales & Surat Jalan',
            self::MonthlyCleaningSpecific => 'Deep Cleaning Item Spesifik Store',
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
            self::MonthlyCleaningChiller, self::MonthlyCleaningFloor,
            self::MonthlyCleaningFreezer, self::MonthlyCleaningSink,
            self::MonthlyCleaningEquipment, self::MonthlyCleaningCoffee,
            self::MonthlyCleaningSofa, self::MonthlyCleaningDiscard,
            self::MonthlyCleaningDocs, self::MonthlyCleaningSpecific => 'Foto Bukti Cleaning',
        };
    }

    public function isGeneralCleaningItem(): bool
    {
        return str_starts_with($this->value, 'monthly_cleaning_');
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
            default => BriefingPeriod::Monthly,
        };
    }

    /** @return self[] */
    public static function forPeriod(BriefingPeriod $period): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $task) => $task->period() === $period && $task !== self::MonthlyGeneralCleaning,
        ));
    }
}
