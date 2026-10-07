<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Default PICs per Branch of a template checkpoint. They pre-fill "Gunakan Template" and are
 * always re-validated (active, Branch access) when the template is applied.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rnd_project_task_template_checkpoint_pics', function (Blueprint $table): void {
            $table->foreignId('rnd_project_task_template_checkpoint_id')
                ->constrained(indexName: 'rnd_task_template_checkpoint_pic_checkpoint_fk')
                ->cascadeOnDelete();
            $table->foreignId('branch_id')
                ->constrained(indexName: 'rnd_task_template_checkpoint_pic_branch_fk')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained(indexName: 'rnd_task_template_checkpoint_pic_user_fk')
                ->cascadeOnDelete();

            $table->primary(['rnd_project_task_template_checkpoint_id', 'branch_id', 'user_id'], 'rnd_task_template_checkpoint_pic_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rnd_project_task_template_checkpoint_pics');
    }
};
