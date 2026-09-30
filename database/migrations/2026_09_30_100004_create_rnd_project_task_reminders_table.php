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
        Schema::create('rnd_project_task_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rnd_project_task_assignment_id')->constrained()->cascadeOnDelete();
            $table->string('reminder_type', 30);
            $table->date('reminder_date');
            $table->timestamp('sent_at')->nullable();
            $table->uuid('notification_id')->nullable();
            $table->timestamps();

            $table->unique(
                ['rnd_project_task_assignment_id', 'reminder_type', 'reminder_date'],
                'rnd_task_reminder_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rnd_project_task_reminders');
    }
};
