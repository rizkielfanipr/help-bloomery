<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/operations/monitoring/prd.md §18.2: one sanitized row per executed check. Pruned after the
 * configured detail retention; daily aggregates are kept separately.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_check_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('monitoring_service_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->timestamp('checked_at');
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('safe_message', 500)->nullable();
            $table->json('metadata')->nullable();

            $table->index(['monitoring_service_id', 'checked_at'], 'monitoring_check_results_service_checked_index');
            $table->index('checked_at', 'monitoring_check_results_checked_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_check_results');
    }
};
