<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('briefing_settings', function (Blueprint $table) {
            $table->date('scoring_started_at')
                ->default('2026-07-20')
                ->after('auto_reject_after_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('briefing_settings', function (Blueprint $table) {
            $table->dropColumn('scoring_started_at');
        });
    }
};
