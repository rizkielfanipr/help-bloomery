<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BriefingTask;
use Illuminate\Database\Seeder;

class MonthlyGeneralCleaningBeforeAfterSeeder extends Seeder
{
    public function run(): void
    {
        // ── Delete old monthly_cleaning_* records ────────────────────────────
        BriefingTask::where(function ($q) {
            $q->where('key', 'like', 'monthly_cleaning_%')
                ->orWhere('key', 'like', 'mthly_%_before')
                ->orWhere('key', 'like', 'mthly_%_after');
        })
            ->whereNotIn('key', BriefingTask::where('key', 'like', 'mthly_%_before_%')
                ->orWhere('key', 'like', 'mthly_%_after_%')
                ->pluck('key')
                ->all())
            ->delete();

        // ── Upsert new before/after poin ─────────────────────────────────────
        $items = [
            ['key_suffix' => 'chiller',    'label' => 'Cuci Komponen Showcase Chiller'],
            ['key_suffix' => 'filter',     'label' => 'Bersihkan Filter Showcase Display'],
            ['key_suffix' => 'floor',      'label' => 'Bersihkan Lantai & Dinding dari Spot Hitam'],
            ['key_suffix' => 'freezer',    'label' => 'Kuras Bunga Es Freezer'],
            ['key_suffix' => 'sink',       'label' => 'Poles Sink & Wastafel'],
            ['key_suffix' => 'equipment',  'label' => 'Deep Cleaning Dispenser, Kompor & Blender'],
            ['key_suffix' => 'coffee',     'label' => 'Deep Cleaning Mesin Kopi'],
            ['key_suffix' => 'sofa',       'label' => 'Vacuum Sofa'],
            ['key_suffix' => 'discard',    'label' => 'Sortir Barang Tidak Terpakai / Diloak'],
            ['key_suffix' => 'specific',   'label' => 'Deep Cleaning Item Spesifik Store'],
        ];

        // null = Global, plus all active branches
        $scopes = collect([null])->merge(Branch::where('is_active', true)->pluck('id'));

        $tasks = [];

        foreach ($scopes as $branchId) {
            $keySuffix = $branchId === null ? 'global' : 'br'.$branchId;

            // All (Before) poin first, then all (After) poin
            $sortBefore = 10;
            foreach ($items as $item) {
                $tasks[] = [
                    'branch_id' => $branchId,
                    'key' => 'mthly_'.$item['key_suffix'].'_before_'.$keySuffix,
                    'label' => '(Before) '.$item['label'],
                    'period' => 'monthly',
                    'submission_type' => 'camera_only',
                    'note_type' => 'Foto Kondisi Sebelum Cleaning',
                    'group' => 'general_cleaning',
                    'group_label' => 'General Cleaning',
                    'sort_order' => $sortBefore,
                    'is_active' => true,
                    'weight' => null,
                    'deadline_enabled' => false,
                    'deadline_day' => null,
                    'deadline_time' => null,
                ];
                $sortBefore += 10;
            }

            $sortAfter = 110;
            foreach ($items as $item) {
                $tasks[] = [
                    'branch_id' => $branchId,
                    'key' => 'mthly_'.$item['key_suffix'].'_after_'.$keySuffix,
                    'label' => '(After) '.$item['label'],
                    'period' => 'monthly',
                    'submission_type' => 'camera_only',
                    'note_type' => 'Foto Kondisi Sesudah Cleaning',
                    'group' => 'general_cleaning',
                    'group_label' => 'General Cleaning',
                    'sort_order' => $sortAfter,
                    'is_active' => true,
                    'weight' => null,
                    'deadline_enabled' => false,
                    'deadline_day' => null,
                    'deadline_time' => null,
                ];
                $sortAfter += 10;
            }
        }

        BriefingTask::upsert(
            $tasks,
            uniqueBy: ['key'],
            update: ['label', 'period', 'submission_type', 'note_type', 'group', 'group_label', 'sort_order', 'is_active', 'deadline_enabled', 'deadline_day', 'deadline_time'],
        );

        $this->command->info('Monthly general cleaning before/after: '.count($tasks).' poin di-upsert ('.$scopes->count().' scope).');
    }
}
