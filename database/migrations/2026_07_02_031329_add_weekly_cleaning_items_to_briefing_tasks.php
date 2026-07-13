<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $newItems = [
        ['key' => 'weekly_cleaning_kondensor', 'label' => 'Membersihkan filter kondensor area showcase display sesuai dengan SOP', 'sort_order' => 4],
        ['key' => 'weekly_cleaning_mesin_kopi', 'label' => 'Membersihkan area mesin kopi menggunakan Puro', 'sort_order' => 5],
        ['key' => 'weekly_cleaning_genset', 'label' => 'Memanaskan genset 15 menit', 'sort_order' => 6],
        ['key' => 'weekly_cleaning_tempat_sampah', 'label' => 'Mencuci tempat sampah & keset', 'sort_order' => 7],
        ['key' => 'weekly_cleaning_showcase_chiller', 'label' => 'Membersihkan showcase Chiller inventory (bagian karet, rak, dan atas unit)', 'sort_order' => 8],
    ];

    /** @var array<int, array{id: int, suffix: string}> */
    private array $branchSuffixes = [
        ['id' => 1, 'suffix' => '_2_2'],
        ['id' => 3, 'suffix' => '_2'],
    ];

    public function up(): void
    {
        // Deactivate old single weekly_cleaning for all branches
        DB::table('briefing_tasks')
            ->whereIn('key', ['weekly_cleaning', 'weekly_cleaning_2', 'weekly_cleaning_2_2'])
            ->update(['is_active' => false]);

        $now = now();
        $base = [
            'period' => 'weekly',
            'submission_type' => 'photo',
            'note_type' => 'Foto Bukti Cleaning',
            'group' => 'weekly_cleaning',
            'group_label' => 'Weekly Cleaning',
            'deadline_time' => null,
            'deadline_day' => null,
            'deadline_enabled' => false,
            'is_active' => true,
            'weight' => null,
            'branch_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $rows = [];

        $existingBranchIds = DB::table('branches')->pluck('id')->all();

        foreach ($this->newItems as $item) {
            // Insert branch-specific copies for each branch that has weekly tasks
            foreach ($this->branchSuffixes as ['id' => $branchId, 'suffix' => $suffix]) {
                if (! in_array($branchId, $existingBranchIds)) {
                    continue;
                }

                $exists = DB::table('briefing_tasks')
                    ->where('key', $item['key'].$suffix)
                    ->exists();

                if (! $exists) {
                    $rows[] = array_merge($base, [
                        'key' => $item['key'].$suffix,
                        'label' => $item['label'],
                        'sort_order' => $item['sort_order'],
                        'branch_id' => $branchId,
                    ]);
                }
            }
        }

        if ($rows !== []) {
            DB::table('briefing_tasks')->insert($rows);
        }
    }

    public function down(): void
    {
        // Reactivate the old weekly_cleaning records
        DB::table('briefing_tasks')
            ->whereIn('key', ['weekly_cleaning', 'weekly_cleaning_2', 'weekly_cleaning_2_2'])
            ->update(['is_active' => true]);

        // Remove the new branch-specific copies
        $keys = [];
        foreach ($this->newItems as $item) {
            foreach ($this->branchSuffixes as ['suffix' => $suffix]) {
                $keys[] = $item['key'].$suffix;
            }
        }

        DB::table('briefing_tasks')->whereIn('key', $keys)->delete();
    }
};
