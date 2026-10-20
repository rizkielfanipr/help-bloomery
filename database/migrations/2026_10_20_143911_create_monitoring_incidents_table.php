<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/system-monitoring-prd.md §16 and §18.5: incident holds the latest state only; its timeline
 * lives in the append-only monitoring_incident_events table. Incidents are never pruned
 * automatically. Permintaan IT is linked through erp_repair_request_id without copying the ticket.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_incidents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('number', 30)->nullable()->unique();
            $table->string('environment', 30);
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('severity', 10);
            $table->string('status', 20)->default('open');
            $table->json('affected_services')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('erp_repair_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_automatic')->default(false);
            $table->timestamp('detected_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedSmallInteger('reopened_count')->default(0);
            $table->text('root_cause')->nullable();
            $table->text('resolution')->nullable();
            $table->text('preventive_action')->nullable();
            $table->timestamps();

            $table->index(['environment', 'status', 'detected_at'], 'monitoring_incidents_env_status_detected_index');
            $table->index(['severity', 'status'], 'monitoring_incidents_severity_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_incidents');
    }
};
