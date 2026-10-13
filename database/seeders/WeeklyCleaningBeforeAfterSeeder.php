<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BriefingTask;
use Illuminate\Database\Seeder;

class WeeklyCleaningBeforeAfterSeeder extends Seeder
{
    public function run(): void
    {
        // ── Delete old/superseded records ────────────────────────────────────
        BriefingTask::where(function ($q) {
            // old weekly_cleaning_* keys (all branches)
            $q->where('key', 'like', 'weekly_cleaning_kondensor%')
                ->orWhere('key', 'like', 'weekly_cleaning_mesin_kopi%')
                ->orWhere('key', 'like', 'weekly_cleaning_genset%')
                ->orWhere('key', 'like', 'weekly_cleaning_tempat_sampah%')
                ->orWhere('key', 'like', 'weekly_cleaning_showcase_chiller%')
                // generic "Weekly Cleaning" task at branch 3
                ->orWhere('key', 'weekly_cleaning_2')
                // first-run wkly_* keys without scope suffix (_global / _brN)
                ->orWhere('key', 'like', 'wkly_%_before')
                ->orWhere('key', 'like', 'wkly_%_after');
        })
            ->whereNotIn('key', BriefingTask::where('key', 'like', 'wkly_%_before_%')
                ->orWhere('key', 'like', 'wkly_%_after_%')
                ->pluck('key')
                ->all())
            ->delete();

        // ── Upsert new before/after poin ─────────────────────────────────────
        $items = [
            ['key_suffix' => 'kondensor',       'label' => 'Filter Kondensor Showcase Display'],
            ['key_suffix' => 'mesin_kopi',       'label' => 'Mesin Kopi (Puro)'],
            ['key_suffix' => 'genset',           'label' => 'Panaskan Genset 15 Menit'],
            ['key_suffix' => 'tempat_sampah',    'label' => 'Tempat Sampah & Keset'],
            ['key_suffix' => 'showcase_chiller', 'label' => 'Showcase Chiller Inventory'],
        ];

        // null = Global, plus all active branches
        $scopes = collect([null])->merge(Branch::where('is_active', true)->pluck('id'));

        $tasks = [];

        foreach ($scopes as $branchId) {
            $keySuffix = $branchId === null ? 'global' : 'br'.$branchId;
            $sort = 10;

            foreach ($items as $item) {
                $tasks[] = [
                    'branch_id' => $branchId,
                    'key' => 'wkly_'.$item['key_suffix'].'_before_'.$keySuffix,
                    'label' => '(Before) '.$item['label'],
                    'period' => 'weekly',
                    'submission_type' => 'camera_only',
                    'note_type' => 'Foto Kondisi Sebelum Cleaning',
                    'group' => 'weekly_cleaning',
                    'group_label' => 'Weekly Cleaning',
                    'sort_order' => $sort,
                    'is_active' => true,
                    'include_in_score' => false,
                    'deadline_enabled' => true,
                    'deadline_day' => 7,
                    'deadline_time' => '23:59',
                ];

                $tasks[] = [
                    'branch_id' => $branchId,
                    'key' => 'wkly_'.$item['key_suffix'].'_after_'.$keySuffix,
                    'label' => '(After) '.$item['label'],
                    'period' => 'weekly',
                    'submission_type' => 'camera_only',
                    'note_type' => 'Foto Kondisi Sesudah Cleaning',
                    'group' => 'weekly_cleaning',
                    'group_label' => 'Weekly Cleaning',
                    'sort_order' => $sort + 1,
                    'is_active' => true,
                    'include_in_score' => false,
                    'deadline_enabled' => true,
                    'deadline_day' => 7,
                    'deadline_time' => '23:59',
                ];

                $sort += 10;
            }
        }

        BriefingTask::upsert(
            $tasks,
            uniqueBy: ['key'],
            update: ['label', 'period', 'submission_type', 'note_type', 'group', 'group_label', 'sort_order', 'is_active', 'deadline_enabled', 'deadline_day', 'deadline_time'],
        );

        $this->command->info('Cleanup selesai. Weekly cleaning before/after: '.count($tasks).' poin di-upsert ('.$scopes->count().' scope).');
    }
}
