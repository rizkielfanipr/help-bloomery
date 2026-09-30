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
        Schema::create('rnd_project_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rnd_project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('task_type', 30);
            $table->text('description')->nullable();
            $table->date('assigned_date');
            $table->date('due_date');
            $table->string('priority', 20)->default('medium');
            $table->string('status', 30)->default('draft');
            $table->json('instruction_attachments')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('rnd_project_id');
            $table->index('status');
            $table->index('due_date');
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rnd_project_tasks');
    }
};
