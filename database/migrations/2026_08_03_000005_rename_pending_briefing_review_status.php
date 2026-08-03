<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('briefing_items')
            ->where('review_status', 'pending')
            ->update(['review_status' => 'supervisor_review']);
    }

    public function down(): void
    {
        DB::table('briefing_items')
            ->where('review_status', 'supervisor_review')
            ->update(['review_status' => 'pending']);
    }
};
