<?php

namespace Database\Seeders;

use App\Models\BriefingTask;
use Illuminate\Database\Seeder;

class WeeklyCleaningBeforeAfterSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['key_suffix' => 'kondensor',       'label' => 'Filter Kondensor Showcase Display'],
            ['key_suffix' => 'mesin_kopi',       'label' => 'Mesin Kopi (Puro)'],
            ['key_suffix' => 'genset',           'label' => 'Panaskan Genset 15 Menit'],
            ['key_suffix' => 'tempat_sampah',    'label' => 'Tempat Sampah & Keset'],
            ['key_suffix' => 'showcase_chiller', 'label' => 'Showcase Chiller Inventory'],
        ];

        $tasks = [];
        $sort = 10;

        foreach ($items as $item) {
            $tasks[] = [
                'branch_id' => null,
                'key' => 'wkly_'.$item['key_suffix'].'_before',
                'label' => $item['label'].' — Foto Sebelum',
                'period' => 'weekly',
                'submission_type' => 'camera_only',
                'note_type' => 'Foto Kondisi Sebelum Cleaning',
                'group' => 'weekly_cleaning',
                'group_label' => 'Weekly Cleaning',
                'sort_order' => $sort,
                'is_active' => true,
                'weight' => null,
                'deadline_enabled' => false,
                'deadline_day' => null,
                'deadline_time' => null,
            ];

            $tasks[] = [
                'branch_id' => null,
                'key' => 'wkly_'.$item['key_suffix'].'_after',
                'label' => $item['label'].' — Foto Sesudah',
                'period' => 'weekly',
                'submission_type' => 'camera_only',
                'note_type' => 'Foto Kondisi Sesudah Cleaning',
                'group' => 'weekly_cleaning',
                'group_label' => 'Weekly Cleaning',
                'sort_order' => $sort + 1,
                'is_active' => true,
                'weight' => null,
                'deadline_enabled' => false,
                'deadline_day' => null,
                'deadline_time' => null,
            ];

            $sort += 10;
        }

        BriefingTask::upsert(
            $tasks,
            uniqueBy: ['key'],
            update: ['label', 'period', 'submission_type', 'note_type', 'group', 'group_label', 'sort_order', 'is_active', 'deadline_enabled', 'deadline_day', 'deadline_time'],
        );

        $this->command->info('Weekly cleaning before/after: '.count($tasks).' poin di-upsert.');
    }
}
