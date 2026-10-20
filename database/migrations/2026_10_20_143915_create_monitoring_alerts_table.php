<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/system-monitoring-prd.md §17: deduplicated alerts. `active_dedup_key` equals `dedup_key`
 * while the alert is active and is cleared on recovery, so the unique index guarantees at most
 * one active alert per environment + service/check/fingerprint key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_alerts', function (Blueprint $table): void {
            $table->id();
            $table->string('environment', 30);
            $table->string('dedup_key', 191);
            $table->string('active_dedup_key', 191)->nullable()->unique();
            $table->string('source_type', 20);
            $table->string('source_key', 120);
            $table->string('title');
            $table->string('severity', 10);
            $table->string('status', 20)->default('active');
            $table->string('safe_message', 500)->nullable();
            $table->foreignId('monitoring_service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('monitoring_incident_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamp('last_notified_at')->nullable();
            $table->unsignedSmallInteger('notification_count')->default(0);
            $table->boolean('suppressed')->default(false);
            $table->timestamps();

            $table->index(['environment', 'status', 'opened_at'], 'monitoring_alerts_env_status_opened_index');
            $table->index('dedup_key', 'monitoring_alerts_dedup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_alerts');
    }
};
