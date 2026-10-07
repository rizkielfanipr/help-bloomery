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
        Schema::create('rnd_project_task_template_checkpoints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rnd_project_task_template_id')
                ->constrained(indexName: 'rnd_task_template_checkpoint_template_fk')
                ->cascadeOnDelete();
            $table->string('title');
            $table->string('task_type', 30);
            $table->text('description')->nullable();
            $table->string('priority', 20)->default('medium');
            $table->smallInteger('assigned_offset_days');
            $table->smallInteger('due_offset_days');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['rnd_project_task_template_id', 'sort_order'], 'rnd_task_template_checkpoint_order_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rnd_project_task_template_checkpoints');
    }
};
