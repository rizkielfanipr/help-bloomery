<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('briefing_tasks')
            ->where('submission_type', 'photo')
            ->update(['submission_type' => 'camera_only', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('briefing_tasks')
            ->where('submission_type', 'camera_only')
            ->whereNotIn('key', ['daily_selfie_pagi', 'daily_selfie_sore'])
            ->update(['submission_type' => 'photo', 'updated_at' => now()]);
    }
};
