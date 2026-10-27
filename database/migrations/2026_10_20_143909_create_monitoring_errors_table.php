<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/products/system-monitoring/prd.md §13 and §18.4: one row per deterministic error fingerprint;
 * repeated occurrences only increment the counter and last-seen timestamp.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_errors', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('environment', 30);
            $table->string('source', 20)->default('backend');
            $table->char('fingerprint', 64);
            $table->string('exception_class', 191);
            $table->string('safe_message', 500)->nullable();
            $table->string('module', 60)->nullable();
            $table->string('route_name', 191)->nullable();
            $table->string('action', 191)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('severity', 20)->default('error');
            $table->string('status', 20)->default('new');
            $table->boolean('is_regression')->default(false);
            $table->unsignedInteger('occurrence_count')->default(0);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->uuid('last_correlation_id')->nullable();
            $table->foreignId('last_actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('first_deploy_commit', 64)->nullable();
            $table->string('last_deploy_commit', 64)->nullable();
            $table->foreignId('triaged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('triaged_at')->nullable();
            $table->text('triage_note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['environment', 'fingerprint'], 'monitoring_errors_env_fingerprint_unique');
            $table->index(['environment', 'status', 'last_seen_at'], 'monitoring_errors_env_status_seen_index');
            $table->index('last_seen_at', 'monitoring_errors_seen_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_errors');
    }
};
