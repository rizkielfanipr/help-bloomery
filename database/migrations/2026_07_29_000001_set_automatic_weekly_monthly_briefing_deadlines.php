<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('briefing_tasks')
            ->where('period', 'weekly')
            ->update([
                'deadline_enabled' => true,
                'deadline_day' => 7,
                'deadline_time' => '23:59:00',
                'updated_at' => now(),
            ]);

        DB::table('briefing_tasks')
            ->where('period', 'monthly')
            ->update([
                'deadline_enabled' => true,
                'deadline_day' => 0,
                'deadline_time' => '23:59:00',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Data deadline sebelumnya tidak dapat direkonstruksi dengan aman.
    }
};
