<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every briefing point now counts toward the branch score — the per-task
     * opt-out (previously used to stop multi-step checklists, like cleaning,
     * from dominating the score) has been removed from the UI, so existing
     * points are backfilled to match.
     */
    public function up(): void
    {
        DB::table('briefing_tasks')->update(['include_in_score' => true]);

        Schema::table('briefing_tasks', function (Blueprint $table) {
            $table->boolean('include_in_score')->default(true)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('briefing_tasks', function (Blueprint $table) {
            $table->boolean('include_in_score')->default(false)->change();
        });
    }
};
