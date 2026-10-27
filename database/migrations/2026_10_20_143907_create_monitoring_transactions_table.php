<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/operations/monitoring/prd.md §11 and §18.3: a parent row per user action and one child row per
 * HTTP mutation attempt. Domain tables stay authoritative; this table only observes. The local
 * record is referenced through `local_model_type` + `local_model_id` without a foreign key so
 * retention never depends on domain tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_transactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('monitoring_transactions')->cascadeOnDelete();
            $table->string('environment', 30);
            $table->string('source_module', 60);
            $table->string('source_action', 60);
            $table->string('operation_type', 60);
            $table->string('target_key', 120)->nullable();
            $table->string('company_code', 20)->nullable();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('local_model_type', 120)->nullable();
            $table->unsignedBigInteger('local_model_id')->nullable();
            $table->string('external_reference', 120)->nullable();
            $table->uuid('correlation_id');
            $table->string('idempotency_key', 120)->nullable();
            $table->string('method', 10)->nullable();
            $table->string('endpoint_template', 191)->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->boolean('mutation_sent')->default(false);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('request_summary')->nullable();
            $table->json('response_summary')->nullable();
            $table->json('timeline')->nullable();
            $table->timestamp('payload_expired_at')->nullable();
            $table->string('error_class', 191)->nullable();
            $table->string('error_code', 60)->nullable();
            $table->string('safe_error_message', 500)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role_snapshot')->nullable();
            $table->string('deploy_commit', 64)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('reconciliation_outcome', 40)->nullable();
            $table->text('reconciliation_note')->nullable();
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['environment', 'parent_id', 'started_at'], 'monitoring_tx_env_parent_started_index');
            $table->index(['environment', 'status', 'started_at'], 'monitoring_tx_env_status_started_index');
            $table->index(['source_module', 'started_at'], 'monitoring_tx_module_started_index');
            $table->index(['company_code', 'started_at'], 'monitoring_tx_company_started_index');
            $table->index(['local_model_type', 'local_model_id'], 'monitoring_tx_local_model_index');
            $table->index('correlation_id', 'monitoring_tx_correlation_index');
            $table->index('external_reference', 'monitoring_tx_external_reference_index');
            $table->index('idempotency_key', 'monitoring_tx_idempotency_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_transactions');
    }
};
