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
        try {
            Schema::table('briefing_scores', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
            });
        } catch (Exception $e) {
            // Foreign key did not exist — safe to continue.
        }

        try {
            Schema::table('briefing_scores', function (Blueprint $table) {
                $table->dropUnique(['branch_id', 'year', 'month']);
            });
        } catch (Exception $e) {
            // Unique constraint did not exist — safe to continue.
        }

        Schema::table('briefing_scores', function (Blueprint $table) {
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->unique(['branch_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::table('briefing_scores', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropUnique(['branch_id', 'year', 'month']);
        });
    }
};
