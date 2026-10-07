<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Default Branches of a template checkpoint — pre-filled when the template is applied and still
 * editable per Project. PICs are never stored on the template.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rnd_project_task_template_checkpoint_branches', function (Blueprint $table): void {
            $table->foreignId('rnd_project_task_template_checkpoint_id')
                ->constrained(indexName: 'rnd_task_template_checkpoint_branch_checkpoint_fk')
                ->cascadeOnDelete();
            $table->foreignId('branch_id')
                ->constrained(indexName: 'rnd_task_template_checkpoint_branch_branch_fk')
                ->cascadeOnDelete();

            $table->primary(['rnd_project_task_template_checkpoint_id', 'branch_id'], 'rnd_task_template_checkpoint_branch_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rnd_project_task_template_checkpoint_branches');
    }
};
