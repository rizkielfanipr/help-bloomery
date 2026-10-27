<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/operations/monitoring/prd.md §18.1: one row per monitored service per environment holding its
 * editable check configuration and the latest evaluated state (snapshot read by the dashboard).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_services', function (Blueprint $table): void {
            $table->id();
            $table->string('environment', 30);
            $table->string('key', 60);
            $table->string('name');
            $table->string('group', 30)->default('core');
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_critical')->default(false);
            $table->unsignedInteger('check_interval_seconds')->default(60);
            $table->unsignedSmallInteger('timeout_seconds')->default(5);
            $table->unsignedSmallInteger('failure_threshold')->default(2);
            $table->unsignedSmallInteger('recovery_threshold')->default(1);
            $table->string('last_status', 20)->default('unknown');
            $table->string('last_observed_status', 20)->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('status_changed_at')->nullable();
            $table->unsignedInteger('last_response_time_ms')->nullable();
            $table->unsignedSmallInteger('last_http_status')->nullable();
            $table->string('last_safe_message', 500)->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->unsignedInteger('consecutive_successes')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['environment', 'key'], 'monitoring_services_env_key_unique');
            $table->index(['environment', 'last_status'], 'monitoring_services_env_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_services');
    }
};
