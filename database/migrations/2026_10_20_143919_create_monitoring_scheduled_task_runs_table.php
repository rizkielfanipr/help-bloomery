<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/products/system-monitoring/prd.md §12.2: run history of scheduled tasks captured from the
 * scheduler's own events. Output is never stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_scheduled_task_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('environment', 30);
            $table->string('task_key', 191);
            $table->string('expression', 60)->nullable();
            $table->string('status', 20);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->smallInteger('exit_code')->nullable();
            $table->string('safe_message', 500)->nullable();

            $table->index(['environment', 'task_key', 'started_at'], 'monitoring_task_runs_env_task_started_index');
            $table->index('started_at', 'monitoring_task_runs_started_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_scheduled_task_runs');
    }
};
