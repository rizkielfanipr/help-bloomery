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
        Schema::create('rnd_project_task_follow_ups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rnd_project_task_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('follow_up_type', 20);
            $table->text('notes')->nullable();
            $table->date('estimated_completion_date')->nullable();
            $table->json('result_attachments')->nullable();
            $table->timestamps();

            $table->index('rnd_project_task_assignment_id');
            $table->index('follow_up_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rnd_project_task_follow_ups');
    }
};
