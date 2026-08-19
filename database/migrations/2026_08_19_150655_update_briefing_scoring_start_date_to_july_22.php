<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('briefing_settings')
            ->whereDate('scoring_started_at', '2026-07-20')
            ->update(['scoring_started_at' => '2026-07-22']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('briefing_settings')
            ->whereDate('scoring_started_at', '2026-07-22')
            ->update(['scoring_started_at' => '2026-07-20']);
    }
};
