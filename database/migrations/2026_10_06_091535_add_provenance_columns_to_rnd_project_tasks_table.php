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
        Schema::table('rnd_project_tasks', function (Blueprint $table): void {
            $table->foreignId('rnd_project_task_template_application_id')
                ->nullable()
                ->after('created_by')
                ->constrained(indexName: 'rnd_project_tasks_template_application_fk')
                ->nullOnDelete();
            $table->foreignId('rnd_project_task_template_checkpoint_id')
                ->nullable()
                ->after('rnd_project_task_template_application_id')
                ->constrained(indexName: 'rnd_project_tasks_template_checkpoint_fk')
                ->nullOnDelete();
            $table->foreignId('copied_from_task_id')
                ->nullable()
                ->after('rnd_project_task_template_checkpoint_id')
                ->constrained('rnd_project_tasks')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rnd_project_tasks', function (Blueprint $table): void {
            $table->dropForeign('rnd_project_tasks_template_application_fk');
            $table->dropForeign('rnd_project_tasks_template_checkpoint_fk');
            $table->dropForeign(['copied_from_task_id']);
            $table->dropColumn([
                'rnd_project_task_template_application_id',
                'rnd_project_task_template_checkpoint_id',
                'copied_from_task_id',
            ]);
        });
    }
};
