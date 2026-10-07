<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Checkpoint dates are now set per Project when a template is applied (bounded by the Project
 * release date), so templates no longer carry day offsets and applications no longer need an
 * anchor date. The final dates of every applied checkpoint stay in `checkpoint_snapshot`.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rnd_project_task_template_checkpoints', function (Blueprint $table): void {
            $table->dropColumn(['assigned_offset_days', 'due_offset_days']);
        });

        Schema::table('rnd_project_task_template_applications', function (Blueprint $table): void {
            $table->dropColumn(['anchor_type', 'anchor_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rnd_project_task_template_checkpoints', function (Blueprint $table): void {
            $table->smallInteger('assigned_offset_days')->default(0)->after('priority');
            $table->smallInteger('due_offset_days')->default(0)->after('assigned_offset_days');
        });

        Schema::table('rnd_project_task_template_applications', function (Blueprint $table): void {
            $table->string('anchor_type', 30)->default('custom')->after('template_name');
            $table->date('anchor_date')->nullable()->after('anchor_type');
        });
    }
};
