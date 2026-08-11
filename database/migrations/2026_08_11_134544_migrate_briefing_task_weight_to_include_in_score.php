<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Scoring moved from a per-task percentage weight to a flat per-period
     * weight (see briefing_period_weights). Individual tasks now only need
     * to say whether they count toward the score at all.
     */
    public function up(): void
    {
        Schema::table('briefing_tasks', function (Blueprint $table) {
            $table->boolean('include_in_score')->default(false)->after('weight');
        });

        DB::table('briefing_tasks')->where('weight', '>', 0)->update(['include_in_score' => true]);

        Schema::table('briefing_tasks', function (Blueprint $table) {
            $table->dropColumn('weight');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('briefing_tasks', function (Blueprint $table) {
            $table->decimal('weight', 5, 2)->nullable()->after('is_active')->comment('Bobot poin dalam penilaian (0-100). Null = tidak masuk penilaian.');
        });

        DB::table('briefing_tasks')->where('include_in_score', true)->update(['weight' => 0]);

        Schema::table('briefing_tasks', function (Blueprint $table) {
            $table->dropColumn('include_in_score');
        });
    }
};
