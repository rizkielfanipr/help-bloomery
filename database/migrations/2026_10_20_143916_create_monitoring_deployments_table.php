<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/system-monitoring-prd.md §14 and §18.6: read-only deployment metadata received through the
 * signed ingestion contract or the local `monitoring:record-deployment` command.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_deployments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('environment', 30);
            $table->string('provider', 40);
            $table->string('run_id', 100);
            $table->string('branch', 120)->nullable();
            $table->string('commit_sha', 64)->nullable();
            $table->string('commit_message', 500)->nullable();
            $table->string('actor', 120)->nullable();
            $table->string('status', 20);
            $table->string('stage', 60)->nullable();
            $table->string('migration_status', 20)->nullable();
            $table->string('health_status', 20)->nullable();
            $table->string('failure_summary', 500)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['environment', 'provider', 'run_id'], 'monitoring_deployments_run_unique');
            $table->index(['environment', 'started_at'], 'monitoring_deployments_env_started_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_deployments');
    }
};
