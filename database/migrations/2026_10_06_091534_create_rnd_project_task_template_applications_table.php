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
        Schema::create('rnd_project_task_template_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rnd_project_id')
                ->constrained(indexName: 'rnd_task_template_application_project_fk')
                ->cascadeOnDelete();
            $table->foreignId('rnd_project_task_template_id')
                ->nullable()
                ->constrained(indexName: 'rnd_task_template_application_template_fk')
                ->nullOnDelete();
            $table->string('template_name');
            $table->string('anchor_type', 30);
            $table->date('anchor_date');
            $table->string('idempotency_key', 64)->unique();
            $table->json('checkpoint_snapshot');
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at');
            $table->timestamps();

            $table->index(['rnd_project_id', 'rnd_project_task_template_id'], 'rnd_task_template_application_lookup_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rnd_project_task_template_applications');
    }
};
