<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable forecast run per branch, cutoff, horizon, algorithm version, and configuration hash
 * (docs/products/analytics/ordering-recommendation/prd.md §11.3, §21). A new run never overwrites an older one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordering_forecast_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('idempotency_key', 191)->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('horizon_days');
            $table->date('history_from');
            $table->date('history_to');
            $table->date('forecast_from');
            $table->date('forecast_to');
            $table->string('algorithm_version', 20);
            $table->string('configuration_hash', 64);
            $table->json('configuration_snapshot');
            $table->timestamp('data_cutoff_at');
            $table->string('status', 20);
            $table->json('completeness')->nullable();
            $table->unsignedInteger('items_count')->default(0);
            $table->timestamp('generated_at')->nullable();
            $table->string('error_summary', 500)->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'horizon_days', 'status', 'generated_at'], 'ordering_forecast_runs_latest_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordering_forecast_runs');
    }
};
