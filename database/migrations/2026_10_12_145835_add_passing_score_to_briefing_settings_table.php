<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * docs/daily-briefing-scoring-ui-prd.md §8.4/§12.1. Default 80, matching the hardcoded
     * threshold `BriefingScore::isPassing()` used before this field existed — existing rows read
     * through a fallback to 80 when their breakdown predates this snapshot field, so this does not
     * change any historical score's achieved/not-achieved status.
     */
    public function up(): void
    {
        Schema::table('briefing_settings', function (Blueprint $table) {
            $table->decimal('passing_score', 5, 2)->default(80)->after('scoring_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('briefing_settings', function (Blueprint $table) {
            $table->dropColumn('passing_score');
        });
    }
};
